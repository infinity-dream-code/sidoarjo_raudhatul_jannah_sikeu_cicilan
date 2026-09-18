<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class mst_sekolah extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = "mst_sekolah";

    protected $primaryKey = "id";

    public $timestamps = false;

    public $incrementing = true;

    protected $guarded = [];
}
