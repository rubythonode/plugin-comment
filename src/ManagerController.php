<?php
namespace Xpressengine\Plugins\Comment;

use App\Http\Controllers\Controller;
use Input;
use Validator;
use Xpressengine\Permission\Grant;
use XePresenter;
use XeConfig;

class ManagerController extends Controller
{
    /**
     * @var Handler
     */
    protected $handler;

    public function __construct()
    {
        $plugin = app('xe.plugin.comment');
        $this->handler = $plugin->getHandler();
        XePresenter::setSettingsSkinTargetId($plugin->getId());
    }

    protected function getInstances()
    {
        $map = XeConfig::get('comment_map');
        $instanceIds = [];
        foreach ($map as $instanceId) {
            $instanceIds[] = $instanceId;
        }

        return $instanceIds;
    }

    public function index()
    {
        Input::flash();

        $model = $this->handler->createModel();
        $query = $model->newQuery()
            ->whereIn('instanceId', $this->getInstances())
            ->where('status', 'public');

        if ($options = Input::get('options')) {
            list($searchField, $searchValue) = explode('|', $options);

            $query->where($searchField, $searchValue);
        }

        $comments = $query->with('target')->paginate();

        $map = $this->handler->getInstanceMap();
        $menuItems = app('xe.menu')->createItemModel()->newQuery()->with('route')
            ->whereIn('id', array_keys($map))->get()->getDictionary();

        return XePresenter::make('index', [
            'comments' => $comments,
            'menuItem' => function ($comment) use ($menuItems, $map) {
                return $menuItems[array_search($comment->instanceId, $map)];
            },
            'urlMake' => function ($comment, $menuItem) {
                $module = app('xe.module');
                return url($module->getModuleObject($menuItem->type)
                        ->getTypeItem($comment->target->targetId)
                        ->getLink($menuItem->route) . '#comment-'.$comment->id);
            },
        ]);
    }

    public function approve()
    {
        $approved = Input::get('approved');
        $commentIds = Input::get('id');
        $commentIds = is_array($commentIds) ? $commentIds : [$commentIds];

        $model = $this->handler->createModel();
        $comments = $model->newQuery()
            ->whereIn('instanceId', $this->getInstances())
            ->whereIn('id', $commentIds)->get();

        foreach ($comments as $comment) {
            $comment->approved = $approved;

            $this->handler->put($comment);
        }

        if (Input::get('redirect') != null) {
            return redirect(Input::get('redirect'));
        } else {
            return redirect()->route('manage.comment.index');
        }
    }

    public function toTrash()
    {
        $commentIds = Input::get('id');
        $commentIds = is_array($commentIds) ? $commentIds : [$commentIds];

        $model = $this->handler->createModel();
        $comments = $model->newQuery()
            ->whereIn('instanceId', $this->getInstances())
            ->whereIn('id', $commentIds)->get();

        foreach ($comments as $comment) {
            $this->handler->trash($comment);
        }

        if (Input::get('redirect') != null) {
            return redirect(Input::get('redirect'));
        } else {
            return redirect()->route('manage.comment.index');
        }
    }

    public function trash()
    {
        Input::flash();

        $model = $this->handler->createModel();
        $comments = $model->newQuery()
            ->whereIn('instanceId', $this->getInstances())
            ->where('status', 'trash')->paginate();

        $map = $this->handler->getInstanceMap();
        $menuItems = app('xe.menu')->createItemModel()->newQuery()->with('route')
            ->whereIn('id', array_keys($map))->get()->getDictionary();

        return XePresenter::make('trash', [
            'comments' => $comments,
            'menuItem' => function ($comment) use ($menuItems, $map) {
                return $menuItems[array_search($comment->instanceId, $map)];
            },
        ]);
    }

    public function destroy()
    {
        $commentIds = Input::get('id');
        $commentIds = is_array($commentIds) ? $commentIds : [$commentIds];

        $model = $this->handler->createModel();
        $comments = $model->newQuery()->whereIn('id', $commentIds)->get();

        foreach ($comments as $comment) {
            $this->handler->remove($comment);
        }

        if (Input::get('redirect') != null) {
            return redirect(Input::get('redirect'));
        } else {
            return redirect()->route('manage.comment.index');
        }
    }

    public function restore()
    {
        $commentIds = Input::get('id');
        $commentIds = is_array($commentIds) ? $commentIds : [$commentIds];

        $model = $this->handler->createModel();
        $comments = $model->newQuery()
            ->whereIn('instanceId', $this->getInstances())
            ->whereIn('id', $commentIds)->get();

        foreach ($comments as $comment) {
            $this->handler->restore($comment);
        }

        if (Input::get('redirect') != null) {
            return redirect(Input::get('redirect'));
        } else {
            return redirect()->route('manage.comment.index');
        }
    }

    public function postSetting()
    {
        $inputs = Input::except(['instanceId', 'redirect', '_token']);

        $configInputs = $permInputs = [];
        foreach ($inputs as $name => $value) {
            if (substr($name, 0, strlen('create')) === 'create'
            || substr($name, 0, strlen('download')) === 'download') {
                $permInputs[$name] = $value;
            } else {
                $configInputs[$name] = $value;
            }
        }

        $validator = Validator::make([
            'instanceId' => Input::get('instanceId'),
            'perPage' => Input::get('perPage')
        ], [
            'instanceId' => 'Required',
            'perPage' => 'Numeric'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors());
        }

        $this->handler->configure(Input::get('instanceId'), $configInputs);

        $grantInfo = [
            'create' => $this->makeGrant($permInputs, 'create'),
            'download' => $this->makeGrant($permInputs, 'download'),
        ];

        $grant = new Grant();
        foreach (array_filter($grantInfo) as $action => $info) {
            $grant->set($action, $info);
        }

        $this->handler->setPermission(Input::get('instanceId'), $grant);

        if (Input::get('redirect') != null) {
            return redirect(Input::get('redirect'));
        } else {
            return redirect()->back();
        }
    }

    private function makeGrant($inputs, $action)
    {
        if (array_get($inputs, $action . 'Mode') === 'inherit') {
            return null;
        }

        return [
            Grant::RATING_TYPE => array_get($inputs, $action . 'Rating'),
            Grant::GROUP_TYPE => array_get($inputs, $action . 'Group') ?: [],
            Grant::USER_TYPE => array_filter(explode(',', array_get($inputs, $action . 'User'))),
            Grant::EXCEPT_TYPE => array_filter(explode(',', array_get($inputs, $action . 'Except'))),
            Grant::VGROUP_TYPE => array_get($inputs, $action . 'VGroup') ?: [],
        ];
    }
}