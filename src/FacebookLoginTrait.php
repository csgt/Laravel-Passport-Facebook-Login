<?php
namespace Danjdewhurst\PassportFacebookLogin;

use Facebook\Facebook;
use Illuminate\Http\Request;
use League\OAuth2\Server\Exception\OAuthServerException;
use Exception;

trait FacebookLoginTrait
{
    /**
     * Logs a App\User in using a Facebook token via Passport
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     * @throws \League\OAuth2\Server\Exception\OAuthServerException
     */
    public function loginFacebook(Request $request)
    {
        try {
            /**
             * Check if the 'fb_token' as passed.
             */
            if ($request->get('fb_token')) {

                /**
                 * Initialise Facebook SDK.
                 */
                $fb = new Facebook([
                    'app_id' => config('facebook.app.id'),
                    'app_secret' => config('facebook.app.secret'),
                    'default_graph_version' => 'v2.5',
                ]);
                $fb->setDefaultAccessToken($request->get('fb_token'));

                /**
                 * Make the Facebook request.
                 */
                $response = $fb->get('/me?locale=en_GB&fields=first_name,last_name,email');
                $fbUser = $response->getDecodedBody();

                /**
                 * Check if the user has already signed up.
                 */
                $userModel = config('auth.providers.users.model');

                /**
                 * Create a new user if they haven't already signed up.
                 */
                $facebook_id_column = config('facebook.registration.facebook_id', 'facebook_id');
                $name_column        = config('facebook.registration.name', 'name');
                $first_name_column  = config('facebook.registration.first_name', 'first_name');
                $last_name_column   = config('facebook.registration.last_name', 'last_name');
                $email_column       = config('facebook.registration.email', 'email');
                $password_column    = config('facebook.registration.password', 'password');

                $user = $userModel::where($facebook_id_column, $fbUser['id'])->first();

                if (!$user) {
                    $user = new $userModel();
                    $user->{$facebook_id_column} = $fbUser['id'];

                    if ($first_name_column) {
                        $user->{$first_name_column} = $fbUser['first_name'];
                    }
                    if ($last_name_column) {
                        $user->{$last_name_column} = $fbUser['last_name'];
                    }
                    if ($name_column) {
                        $user->{$name_column} = $fbUser['first_name'] . ' ' . $fbUser['last_name'];
                    }

                    $user->{$email_column}    = $fbUser['email'];
                    $user->{$password_column} = bcrypt(uniqid('fb_', true)); // Random password.
                    $user->save();

                    //Si es quiniela lepegue, lo inscribimos automaticamente
                    try {
                        if ($request->input('quinielaid', 0) === '1') {
                            $uq = new App\Models\Usuarioquiniela;
                            $uq->usuarioid = $user->usuarioid;
                            $uq->quinielaid = 1;
                            $uq->nombre = 'Auto por registro';
                            $uq->casillas = 48;
                            $uq->save();
                        }
                    } catch (Exception $e) {
                    }


                    /**
                     * Attach a role to the user.
                     */
                    if (!is_null(config('facebook.registration.attach_role'))) {
                        $user->attachRole(config('facebook.registration.attach_role'));
                    }
                }

                return $user;
            }
        } catch (\Exception $e) {
            throw OAuthServerException::accessDenied($e->getMessage());
        }
        return null;
    }
}
