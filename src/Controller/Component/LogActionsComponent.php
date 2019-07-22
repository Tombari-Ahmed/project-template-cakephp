<?php
namespace App\Controller\Component;

use App\Event\AuditViewEvent;
use AuditStash\PersisterInterface;
use AuditStash\Persister\ElasticSearchPersister;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\ORM\Table;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use Webmozart\Assert\Assert;

/**
 * LogActions component
 */
class LogActionsComponent extends Component
{
    /**
     * Before render callback.
     *
     * @param \Cake\Event\Event $event The beforeRender event.
     * @return void
     */
    public function beforeFilter(Event $event) : void
    {
        $controllers = Configure::read('LogActions.controllers');
        $actions = Configure::read('LogActions.excludeActions');

        if (empty($controllers)) {
            return;
        }

        if (!in_array($this->request->getParam('controller'), $controllers) && in_array($this->request->getParam('action'), $actions)) {
            return;
        }

        $user_id = $this->_registry->getController()->Auth->user('id');

        $controller = $this->getController();
        $request = $controller->request;
        $table = $this->getController()->loadModel();
        Assert::isInstanceOf($table, Table::class);

        $meta = [
            'action' => $request->getParam('action'),
            'pass' => empty($request->getParam('pass')[0]) ? '' : $request->getParam('pass')[0]
        ];

        $primary = empty($request->getParam('pass')[0]) ? 'index' : $request->getParam('pass')[0];

        $event = new AuditViewEvent(Text::uuid(), $primary, $table->getAlias(), [], []);
        $event->setMetaInfo($meta);

        $data = $controller->dispatchEvent('AuditStash.beforeLog', ['logs' => [$event]]);
        $this->getPersister()->logEvents($data->getData('logs'));
    }

    /**
     * Initiates a new persister object to use for logging view audit events.
     *
     * @return PersisterInterface The configured persister
     */
    private function getPersister(): PersisterInterface
    {
        $class = Configure::read('AuditStash.persister') ?: ElasticSearchPersister::class;
        $index = $this->getConfig('index') ?: $this->getController()->loadModel()->getAlias();
        $type = $this->getConfig('type') ?: Inflector::singularize($index);

        return new $class(compact('index', 'type'));
    }
}
