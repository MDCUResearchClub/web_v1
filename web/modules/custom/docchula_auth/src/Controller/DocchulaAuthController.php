<?php

namespace Drupal\docchula_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\Request;

/**
 *  Docchula Auth controller.
 */
class DocchulaAuthController extends ControllerBase {

  private function get_google_client() {
    $config = $this->config('docchula_auth.settings');

    $client = new \Google_Client();
    $client->setAuthConfig($config->get('credentials_path'));
    $client->addScope(["openid", "email", "profile"]);
    $client->setRedirectUri(Url::fromRoute('docchula_auth.callback')->setAbsolute()->toString(True)->getGeneratedUrl());
    $client->setHostedDomain($config->get('hosted_domain'));

    return $client;
  }

  /**
   *
   * Redirects the user to google for authentication.
   *
   * This is done in a render context in order to bubble cacheable metadata
   * created during authentication URL generation.
   *
   */
  public function redirectToProvider() {
    $auth_url = $this->get_google_client()->createAuthUrl();

    return new TrustedRedirectResponse($auth_url);
  }

  /**
  * Response for path 'user/login/docchula/callback'.
  *
  * Google returns the user here after user has authenticated.
  */
  public function callback(Request $request) {
    $query = $request->query;
    if ($query->has("error")) {
      $this->messenger()->addError($this->t('You could not be authenticated.'));
      return $this->redirect('user.login');
    }

    if (!$query->has("code")) {
      $this->messenger()->addError($this->t('You could not be authenticated.'));
      return $this->redirect('user.login');
    }

    $code = $query->get("code");
    $client = $this->get_google_client();
    $client->authenticate($code);

    $userinfo = (new \Google_Service_Oauth2($client))->userinfo->get();
    $email = $userinfo->getEmail();
    $username = $userinfo->getName();
    $picture_url = $userinfo->getPicture();

    $user = user_load_by_mail($email);

    // Create new user
    if (!$user) {
      $user = User::create();

      // Mandatory.
      $user->setPassword(user_password(64));
      $user->enforceIsNew();
      $user->setEmail($email);
      $user->setUsername($username);

      // Optional.
      $user->addRole('docchula');
      $user->activate();

      // Save user account.
      $result = $user->save();

      $this->messenger()->addMessage($this->t('Welcome @username to MDCU Research Club', [
        "@username" => $username,
      ]));

      $this->getlogger("docchula_auth")->notice('New user created. Username @username, UID: @uid', [
        '@username' => $username,
        '@uid' => $user->id(),
      ]);
    }

    if (user_picture_enabled()) {
      // Mark old picture as Temporary
      $old_file_id = $user->get('user_picture')->value;
      if ($old_file_id) {
        // file_delete($old_file_id);
        File::load($old_file_id)->setTemporary();
      }

      $file_directory = $user->getFieldDefinition('user_picture')->getSetting('file_directory');
      $directory = \Drupal::service('token')->replace(dirname($file_directory));
      $filename = \Drupal::service('token')->replace(basename($file_directory));
      $scheme = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions("user")["user_picture"]->getSetting("uri_scheme");
      $full_directory = $scheme . "://" . $directory;
      $destination = $full_directory . DIRECTORY_SEPARATOR . "docchula_auth" . DIRECTORY_SEPARATOR . $user->id() . "_". $filename . ".jpg";
      if (\Drupal::service('file_system')->prepareDirectory($full_directory, FileSystemInterface::CREATE_DIRECTORY)) {
        $file = system_retrieve_file($picture_url, $destination, True, FileSystemInterface::EXISTS_REPLACE);
        $user->set('user_picture', $file->id());
        $user->save();
      } else {
        $this->getlogger("docchula_auth")
          ->error('Could not save profile picture. Directory is not writable: @directory', [
            '@directory' => $directory,
          ]);
      }
    }

    user_login_finalize($user);

    return $this->redirect("<front>");
  }
}
