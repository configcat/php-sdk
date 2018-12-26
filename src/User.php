<?php

namespace ConfigCat;

use InvalidArgumentException;

/**
 * Class User An object containing attributes to properly identify a given user for rollout evaluation.
 * @package ConfigCat
 */
final class User
{
    /** @var array */
   private $attributes = [];
   /** @var string */
   private $identifier;

    /**
     * User constructor.
     *
     * @param string $identifier The identifier of the user.
     * @param string $email Optional. The email of the user.
     * @param string $country Optional. The country attribute of the user.
     * @param array $custom Custom user attributes.
     *
     * @throws InvalidArgumentException
     *   If the $identifier is not a legal value.
     */
   public function __construct($identifier, $email = "", $country = "", $custom = [])
   {
       if(empty($identifier)) {
           throw new InvalidArgumentException("identifier cannot be empty.");
       }

       $this->identifier = $this->attributes['identifier'] = $identifier;

       if(!empty($email)) {
           $this->attributes['email'] = $email;
       }

       if(!empty($country)) {
           $this->attributes['country'] = $country;
       }

       if(!empty($custom)) {
           $this->attributes = array_merge($this->attributes, array_change_key_case($custom, CASE_LOWER));
       }
   }

    /**
     * Gets the identifier of the user.
     *
     * @return string The identifier of the user.
     */
   public function getIdentifier()
   {
       return $this->identifier;
   }

    /**
     * Gets a user attribute identified by the given key.
     *
     * @param string $key The key of the user attribute.
     * @return string|null The user attribute, or null if it doesn't exist.
     *
     * @throws InvalidArgumentException
     *   If the $key is not a legal value.
     */
   public function getAttribute($key)
   {
       if(empty($key)) {
           throw new InvalidArgumentException("key cannot be empty.");
       }

       $lowerCaseKey = strtolower($key);
       return array_key_exists($lowerCaseKey, $this->attributes) ? $this->attributes[$lowerCaseKey] : null;
   }
}