<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $this->ensureRegistrationAllowed($request);

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureRegistrationAllowed($request);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        // المدير ينشئ حسابًا لمستخدم آخر: لا نبدّل جلسته، ونوجّهه لتعيين الأدوار
        if ($request->user()) {
            return redirect()->route('users.roles.edit', $user);
        }

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * التسجيل الذاتي مغلق: مسموح فقط عند الإعداد الأول (لا يوجد أي مستخدم)
     * أو لمستخدم مسجّل لديه صلاحية "register" (المدير يمرّ عبر Gate::before).
     */
    protected function ensureRegistrationAllowed(Request $request): void
    {
        $user = $request->user();

        if ($user) {
            abort_unless($user->can('register'), 403);

            return;
        }

        abort_if(User::query()->exists(), 403);
    }
}
