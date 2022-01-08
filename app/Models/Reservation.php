<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $table = 'reservations';

    public function train()
    {
        return $this->hasOne(Train::class, 'id', 'train_id');
    }

    public function vagon()
    {
        return $this->hasOne(Vagon::class, 'id', 'vagon_id');
    }
}
