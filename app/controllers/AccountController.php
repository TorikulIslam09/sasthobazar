<?php
declare(strict_types=1);

/** Customer account: profile (name, email, gender, birthday, photo, language) and password. */
class AccountController extends Controller
{
    public const AVATAR_MAX_BYTES = 2 * 1024 * 1024;

    public function index(): void
    {
        $uid = (int) Auth::id();
        $user = User::find($uid);
        $old = $_SESSION['_old'] ?? null;
        $this->view('account/profile', [
            'title'          => __('my_account'),
            'tab'            => 'profile',
            'user'           => $user,
            'input'          => is_array($old) ? array_merge($user, $old) : $user,
            'errors'         => $_SESSION['_errors'] ?? [],
            'addressCount'   => Address::countForUser($uid),
            'wishlistCount'  => Wishlist::countFor($uid),
            'defaultAddress' => Address::forUser($uid)[0] ?? null,
        ]);
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function update(): void
    {
        $user = User::find((int) Auth::id());
        $in = $this->postData();
        $errors = $this->validate($in, [
            'name'          => 'required|max:150',
            'email'         => 'email|max:191',
            'gender'        => 'in:male,female,other',
            'date_of_birth' => 'date',
            'lang'          => 'required|in:bn,en',
        ]);
        $email = strtolower($in['email'] ?? '');
        if (!isset($errors['email']) && $email !== '' && User::emailExists($email, (int) $user['id'])) {
            $errors['email'] = __('email_taken');
        }
        $dob = $in['date_of_birth'] ?? '';
        if (!isset($errors['date_of_birth']) && $dob !== '' && ($dob > date('Y-m-d') || $dob < '1900-01-01')) {
            $errors['date_of_birth'] = __('v_dob');
        }

        $avatar = Upload::files('avatar')[0] ?? null;
        if ($avatar && ($err = Upload::validateImage($avatar, self::AVATAR_MAX_BYTES))) {
            $errors['avatar'] = $err;
        }

        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('account');
        }

        $data = [
            'name'          => $in['name'],
            'email'         => $email !== '' ? $email : null,
            'gender'        => ($in['gender'] ?? '') !== '' ? $in['gender'] : null,
            'date_of_birth' => $dob !== '' ? $dob : null,
            'lang'          => $in['lang'],
        ];
        if ($avatar) {
            $data['avatar'] = Upload::storeImage($avatar, 'avatars');
        } elseif (!empty($in['remove_avatar'])) {
            $data['avatar'] = null;
        }
        User::updateById((int) $user['id'], $data);
        if (array_key_exists('avatar', $data) && $user['avatar']) {
            Upload::delete($user['avatar']);
        }
        $_SESSION['lang'] = $in['lang'];
        flash('success', __('profile_saved'));
        redirect('account');
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
            flash('error', __('form_has_errors'));
            redirect('account#password');
        }
        $hash = password_hash($in['password'], PASSWORD_DEFAULT);
        User::updateById((int) $user['id'], ['password_hash' => $hash]);
        Auth::refreshPasswordFingerprint($hash); // keep this session, sign out other devices
        flash('success', __('password_changed'));
        redirect('account');
    }
}
