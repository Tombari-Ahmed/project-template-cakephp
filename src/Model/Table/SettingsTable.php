<?php
namespace App\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Validation\Validator;
use CsvMigrations\FieldHandlers\Config\ConfigFactory;
use CsvMigrations\FieldHandlers\CsvField;

/**
 * Settings Model
 *
 * @method \App\Model\Entity\Setting get($primaryKey, $options = [])
 * @method \App\Model\Entity\Setting newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Setting[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Setting|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Setting patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Setting[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Setting findOrCreate($search, callable $callback = null, $options = [])
 */
class SettingsTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('settings');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->scalar('key')
            ->maxLength('key', 255)
            ->requirePresence('key', 'create')
            ->notEmpty('key')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('value')
            ->maxLength('value', 255)
            ->requirePresence('value', 'create')
            ->allowEmpty('value');

        $validator->add('value', 'custom', [
            'rule' => [$this, 'settingsValidator'],
        ]);

        return $validator;
    }

    /**
     * Validate the field from the type in settings.php
     * @param value $value Value of the field
     * @param entity $context The entity
     * @return bool True if validate
     */
    public function settingsValidator($value, $context)
    {
        $type = $context['data']['type'];
        $config = ConfigFactory::getByType($type, 'value');
        $validationClass = $config->getProvider('validationRules');
        $validationRules = new $validationClass($config);

        $validator = $validationRules->provide(new Validator(), [
            'fieldDefinitions' => new CsvField(['name' => 'value'])
        ]);

        return empty($validator->errors(['value' => $value]));
    }

    /**
     * Get all the Setting configuration and filter it base on the user
     * roles describe in settings.php
     *
     * @param array $dataSettings Data to filter
     * @param array $userRoles list of roles of the user
     * @return array Settings onw by the user
     * @throws \RuntimeException when settings.php structure is broke
     */
    public function filterSettings($dataSettings, $userRoles)
    {
        $filter = array_filter(Hash::flatten($dataSettings), function ($value) use ($userRoles) {
            return in_array($value, $userRoles);
        });
        $dataFlatten = [];
         foreach ($filter as $key => $value) {
            $p = explode('.', $key);
            // ex: 'Config.UI.Theme.Title.roles.0'
            // the stucture must be 4 defalut layer plus two
            if (count($p) < 6) {
                throw new \RuntimeException("broken configuration in Settings");
            }
            $p = $p[0] . '.' . $p[1] . '.' . $p[2] . '.' . $p[3];
            $dataFlatten[$p] = Hash::extract($dataSettings, $p);
        }
        // $dataFiltered has now only fields belonging to the user roles
        $dataFiltered = Hash::expand($dataFlatten);
         return $dataFiltered;
    }

    /**
     * getAliasDiff() return the missing alias in the DB
     *
     * @param array $settings Array with settings
     * @return array
     */
    public function getAliasDiff($settings = [])
    {
        // Array with all the alias from the config
        $alias = [];
        foreach ($settings as $data) {
            // check is the alias exist in the Configure
            Configure::readOrFail($data);
            $alias[] = $data;
        }

        // Array with all the alias from the db
        $fromDB = $this->find()->extract('key')->toArray();
        $diff = array_values(array_diff($alias, $fromDB));

        return $diff;
    }
}
