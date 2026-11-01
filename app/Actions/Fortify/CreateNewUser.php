<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * Validate and create a newly registered user.
     *
     * ルールは data.md 2-1（name は30字）と screens.md 4-3。
     * 文言は screens.md 4-3 の表から1対1で写す（behavior.md 3-8）。
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:30'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string'],
        ], [
            'name.required' => '表示名を入力してください',
            'name.max' => '表示名は30文字以内で入力してください',
            'email.required' => 'メールアドレスを入力してください',
            'email.email' => 'メールアドレスの形式が正しくありません',
            'email.unique' => 'このメールアドレスはすでに使われています',
            'password.required' => 'パスワードを入力してください',
            'password.min' => 'パスワードは8文字以上で入力してください',
            'password_confirmation.required' => 'パスワード（確認用）を入力してください',
            'password_confirmation.same' => 'パスワードが一致しません',
        ]);

        // 一致の検査は「パスワード（確認用）」側に付ける。screens.md 4-3 はこの文言を
        // 確認用の行に置いており、4-1 は「その入力欄の下」に出すと決めているため。
        // 両方が埋まっているときだけ見るのは、片方が空のときに2つの欄が同時に赤くならない
        // ようにするため（acceptance.md T-1-01「その欄の下にだけエラー文が出る」）。
        $validator->sometimes('password_confirmation', 'same:password', function ($input) {
            return filled($input->password) && filled($input->password_confirmation);
        });

        $validator->validate();

        return User::create([
            'name' => $input['name'],
            // ログイン側は CanonicalizeUsername が小文字にして引く（config/fortify.php の
            // lowercase_usernames）。保存も小文字に揃えて、照合順序に頼らないようにする。
            'email' => Str::lower($input['email']),
            'password' => $input['password'],
        ]);
    }
}
