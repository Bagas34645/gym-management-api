<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WeatherProcessed extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'weather_processed';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
