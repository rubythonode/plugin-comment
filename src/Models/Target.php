<?php
namespace Xpressengine\Plugins\Comment\Models;

use Xpressengine\Database\Eloquent\DynamicModel;
use Xpressengine\User\Models\UnknownUser;
use Xpressengine\User\Models\User;
use Xpressengine\User\UserInterface;

/**
 * Class Target
 *
 * @property Comment $comment
 * @property User|null $author
 *
 * @package Xpressengine\Plugins\Comment\Models
 */
class Target extends DynamicModel
{
    protected $table = 'comment_target';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['docId', 'targetId', 'targetAuthorId'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'docId');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'targetAuthorId');
    }

    /**
     * Returns the author
     *
     * @return UserInterface
     */
    public function getAuthor()
    {
        if (!$author = $this->getRelationValue('author')) {
            $author = new UnknownUser();
        }

        return $author;
    }
}
