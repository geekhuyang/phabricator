<?php

final class PhabricatorUserPreferencesCacheType
  extends PhabricatorUserCacheType {

  const CACHETYPE = 'preferences';

  const KEY_PREFERENCES = 'user.preferences.v1';

  public function getAutoloadKeys() {
    return array(
      self::KEY_PREFERENCES,
    );
  }

  public function canManageKey($key) {
    return ($key === self::KEY_PREFERENCES);
  }

  public function getValueFromStorage($value) {
    return phutil_json_decode($value);
  }

  public function newValueForUsers($key, array $users) {
    $viewer = $this->getViewer();

    $users = mpull($users, null, 'getPHID');
    $user_phids = array_keys($users);

    $preferences = id(new PhabricatorUserPreferencesQuery())
      ->setViewer($viewer)
      ->withUserPHIDs($user_phids)
      ->execute();

    $all_settings = PhabricatorSetting::getAllSettings();

    $settings = array();
    foreach ($preferences as $preference) {
      $user_phid = $preference->getUserPHID();
      foreach ($all_settings as $key => $setting) {
        $value = $preference->getSettingValue($key);

        // As an optimization, we omit the value from the cache if it is
        // exactly the same as the hardcoded default.
        $default_value = id(clone $setting)
          ->setViewer($users[$user_phid])
          ->getSettingDefaultValue();
        if ($value === $default_value) {
          continue;
        }

        $settings[$user_phid][$key] = $value;
      }
    }

    $results = array();
    foreach ($user_phids as $user_phid) {
      $value = idx($settings, $user_phid, array());
      $results[$user_phid] = phutil_json_encode($value);
    }

    return $results;
  }

}
