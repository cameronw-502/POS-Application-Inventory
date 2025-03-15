<?php
// filepath: c:\laragon\www\laravel-app\laravel-app\app\Auth\RegisterTokenGuard.php
namespace App\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use App\Models\RegisterApiKey;

class RegisterTokenGuard implements Guard
{
    use GuardHelpers;
    
    protected $request;
    protected $inputKey;
    protected $storageKey;
    
    public function __construct(Request $request, $inputKey = 'api_key', $storageKey = 'key')
    {
        $this->request = $request;
        $this->inputKey = $inputKey;
        $this->storageKey = $storageKey;
    }
    
    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }
        
        $token = $this->getTokenForRequest();
        
        if (!empty($token)) {
            $this->user = RegisterApiKey::where($this->storageKey, $token)
                ->where('is_active', true)
                ->where(function($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->first();
        }
        
        return $this->user;
    }
    
    protected function getTokenForRequest()
    {
        $token = $this->request->query($this->inputKey);
        
        if (empty($token)) {
            $token = $this->request->input($this->inputKey);
        }
        
        if (empty($token)) {
            $token = $this->request->bearerToken();
        }
        
        return $token;
    }
    
    public function validate(array $credentials = [])
    {
        return !is_null($this->user());
    }
}