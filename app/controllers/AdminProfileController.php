<?php
declare(strict_types=1);

/** The logged-in staff member's own profile and password. */
class AdminProfileController extends Controller
{
    public function index(): void
    {
        $user = User::find((int) Auth::id());
        $this->view('admin/profile/index', [
            'title'  => __('my_profile'),
            'user'   => $user,
            'input'  => $user,
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
    }

    public function update(): void
    {
        $user = User::find((int) Auth::id());
        $in = $this->postData();
        $errors = $this->validate($in, ['name' => 'required|max:150', 'phone' => 'phone', 'lang' => 'required|in:bn,en']);
        if (!isset($errors['phone']) && ($in['phone'] ?? '') !== '' && User::phoneExists($in['phone'], (int) $user['id'])) {
            $errors['phone'] = __('phone_taken');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            redirect('admin/profile');
        }
        User::updateById((int) $user['id'], [
            'name'  => $in['name'],
            'phone' => ($in['phone'] ?? '') !== '' ? Auth::normalizePhone($in['phone']) : null,
            'lang'  => $in['lang'],
        ]);
        $_SESSION['lang'] = $in['lang'];
        flash('success', __('profile_saved'));
        redirect('admin/profile');
    }

    public function password(): void
    {
        $user = User::find((int) Auth::id());
        $in = $this->postData();
        $errors = $this->validate($in, ['current_password' => 'required', 'password' => 'required|min:8|max:72|confirmed']);
        if (!isset($errors['current_password']) && !password_verify($in['current_password'], $user['password_hash'])) {
            $errors['current_password'] = __('wrong_current_password');
        }
        if (!$errors && password_verify($in['password'], $user['password_hash'])) {
            $errors['password'] = __('password_same');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            redirect('admin/profile');
        }
        $hash = password_hash($in['password'], PASSWORD_DEFAULT);
        User::updateById((int) $user['id'], ['password_hash' => $hash]);
        Auth::refreshPasswordFingerprint($hash); // keep this session, log out all others
        flash('success', __('password_changed'));
        redirect('admin/profile');
    }
}
