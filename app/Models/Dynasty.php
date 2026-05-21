<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dynasty extends Model
{
    protected $table = 'dynasties';

    public $timestamps = false;

    protected $fillable = ['name'];
}
