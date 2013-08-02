<?php

abstract class HeraldAdapter {

  abstract public function getPHID();
  abstract public function getHeraldName();
  abstract public function getHeraldTypeName();
  abstract public function getHeraldField($field_name);
  abstract public function applyHeraldEffects(array $effects);

  public function isEnabled() {
    return true;
  }

  /**
   * NOTE: You generally should not override this; it exists to support legacy
   * adapters which had hard-coded content types.
   */
  public function getAdapterContentType() {
    return get_class($this);
  }

  abstract public function getAdapterContentName();

  public static function applyFlagEffect(HeraldEffect $effect, $phid) {
    $color = $effect->getTarget();

    // TODO: Silly that we need to load this again here.
    $rule = id(new HeraldRule())->load($effect->getRuleID());
    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $rule->getAuthorPHID());

    $flag = PhabricatorFlagQuery::loadUserFlag($user, $phid);
    if ($flag) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        pht('Object already flagged.'));
    }

    $handle = PhabricatorObjectHandleData::loadOneHandle(
      $phid,
      $user);

    $flag = new PhabricatorFlag();
    $flag->setOwnerPHID($user->getPHID());
    $flag->setType($handle->getType());
    $flag->setObjectPHID($handle->getPHID());

    // TOOD: Should really be transcript PHID, but it doesn't exist yet.
    $flag->setReasonPHID($user->getPHID());

    $flag->setColor($color);
    $flag->setNote(
      pht('Flagged by Herald Rule "%s".', $rule->getName()));
    $flag->save();

    return new HeraldApplyTranscript(
      $effect,
      true,
      pht('Added flag.'));
  }

  public static function getAllAdapters() {
    static $adapters;
    if (!$adapters) {
      $adapters = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();
    }
    return $adapters;
  }

  public static function getAllEnabledAdapters() {
    $adapters = self::getAllAdapters();
    foreach ($adapters as $key => $adapter) {
      if (!$adapter->isEnabled()) {
        unset($adapters[$key]);
      }
    }
    return $adapters;
  }


}

