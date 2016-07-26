<?php namespace Sofa\Revisionable\Laravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class Revision extends Model
{
    /**
     * Action executor user model.
     *
     * @var string
     */
    protected static $userModel;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected static $customTable;

    /**
     * Allow mass assignement.
     *
     * @var array
     */
    protected $fillable = ['*'];

    /**
     * {@inheritdoc}
     */
    public static function boot()
    {
        parent::boot();

        // Make it read-only
        static::saving(function () {
            return false;
        });
    }

    /**
     * Revision belongs to User (action Executor)
     * @link https://laravel.com/docs/eloquent-relationships#one-to-one
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function executor()
    {
        return $this->belongsTo(static::$userModel, 'user');
    }

    /**
     * Get array of updated fields.
     *
     * @return array
     */
    public function getUpdated()
    {
        return array_keys(array_diff_assoc($this->new, $this->old));
    }

    /**
     * Get diff of the old/new arrays.
     *
     * @return array
     */
    public function getDiff()
    {
        $diff = [];

        foreach ($this->getUpdated() as $key) {
            $diff[$key]['old'] = $this->old($key);

            $diff[$key]['new'] = $this->new($key);
        }

        return $diff;
    }

    /**
     * Determine whether field was updated during current action.
     *
     * @param  string $key
     * @return boolean
     */
    public function isUpdated($key)
    {
        return in_array($key, $this->getUpdated());
    }

    /**
     * Accessor for old property
     *
     * @return array
     */
    public function getOldAttribute($old)
    {
        return (array) json_decode($old);
    }

    /**
     * Accessor for new property
     *
     * @return array
     */
    public function getNewAttribute($new)
    {
        return (array) json_decode($new);
    }

    /**
     * Get single value from the new/old array.
     *
     * @param  string $version
     * @param  string $key
     * @return string
     */
    protected function getFromArray($version, $key)
    {
        return array_get($this->{$version}, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function getTable()
    {
        $table = $this->table ?: static::$customTable;

        return ($table) ?: parent::getTable();
    }

    /**
     * Set custom table name for the model.
     *
     * @param  string  $table
     * @return void
     */
    public static function setCustomTable($table)
    {
        if (!isset(static::$customTable)) {
            static::$customTable = $table;
        }
    }

    /**
     * Set user model.
     *
     * @param  string  $class
     * @return void
     */
    public static function setUserModel($class)
    {
        static::$userModel = $class;
    }

    /**
     * Query scope ordered
     * @link https://laravel.com/docs/eloquent#local-scopes
     *
     * @param  \Illuminate\Database\Eloquent\Builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->latest()->latest('id');
    }

    /**
     * Query scope for.
     * @link https://laravel.com/docs/eloquent#local-scopes
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  \Illuminate\Database\Eloquent\Model|string $table
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFor($query, $table)
    {
        if ($table instanceof Model) {
            $table = $table->getTable();
        }

        return $query->where('table_name', $table);
    }

    /**
     * Handle dynamic method calls.
     *
     * @param  string $method
     * @param  array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, ['new', 'old'])) {
            array_unshift($parameters, $method);

            return call_user_func_array([$this, 'getFromArray'], $parameters);
        }

        if ($method == 'label') {
            return reset($parameters);
        }

        return parent::__call($method, $parameters);
    }
}
