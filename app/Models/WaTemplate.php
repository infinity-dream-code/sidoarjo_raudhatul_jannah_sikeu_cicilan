<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaTemplate extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = "wa_template";

    protected $fillable = [
        "kode",
        "nama",
        "template",
        "is_active",
    ];

    protected $casts = [
        "is_active" => "integer",
    ];
}
