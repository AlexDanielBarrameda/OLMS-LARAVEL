<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use App\Models\User;

#[Layout('components.layouts.app')] 
class Login extends Component
{
    public $isLogin = true; 
    public $email, $password, $name;
    public $password_confirmation;
    
    // Default to 'student' for safety based on your new RBAC system.
    public $role = 'student'; 

    // The Login/Register tabs use $set('isLogin'), so clear errors from the other tab here
    public function updatedIsLogin()
    {
        $this->resetErrorBag();
    }

    public function toggleMode()
    {
        $this->isLogin = !$this->isLogin;
        $this->resetErrorBag();
    }

    public function authenticate()
    {
        // -----------------------
        // LOGIN LOGIC
        // -----------------------
        if ($this->isLogin) {
            $this->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
                session()->regenerate();
                $user = Auth::user();

                Notification::make()
                    ->title('Login Successful')
                    ->body('Welcome back, ' . $user->name . '!')
                    ->success()
                    ->send();

                // LOGIC CHECK: Route based on the new roles!
                // Both Super Admins and Librarians go to the Admin Panel.
                if ($user->isSuperAdmin() || $user->isLibrarian()) {
                    return redirect()->to('/admin');
                }

                // Students and everyone else go to the User Panel.
                return redirect()->to('/user');
            }

            Notification::make()
                ->title('Invalid credentials')
                ->body('The email or password you entered is incorrect.')
                ->danger()
                ->send();
        } 
        // -----------------------
        // REGISTRATION LOGIC
        // -----------------------
        else {
            // 1. Validate the new account details
            $this->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email', 
                // Must match password_confirmation, so a typo can't lock the user out of a new account
                'password' => 'required|min:8|confirmed',
            ]);

            // 2. Create the new user in the database
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password, 
                'role' => 'student', // Default role for all new signups
                'status' => 'active',
            ]);

            // 3. Log the new user in automatically
            Auth::login($user);
            session()->regenerate();

            // 4. Welcome them
            Notification::make()
                ->title('Account Created!')
                ->body('Welcome to Book Garden Library, ' . $user->name . '!')
                ->success()
                ->send();

            // 5. Send them straight to the user panel
            return redirect()->to('/user');
        }
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}