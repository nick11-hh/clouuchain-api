<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthClients extends Model
{
    use HasFactory;
    protected $table = 'oauth_clients';
    protected $fillable = ['user_id', 'name', 'secret', 'redirect', 'personal_access_client', 'password_client', 'revoked', 'license_url'];
}
