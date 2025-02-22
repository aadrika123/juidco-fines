<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopPayment extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'mar_shop_payments';
    protected $connection = "pgsql_advertisements";
}
