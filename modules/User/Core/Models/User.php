<?php

namespace Modules\User\Core\Models;

class User
{
    private $id;
    private $fullname;
    private $email;
    private $photo;
    private $password;
    private $role;
    private $status;
    private $verified_email;
    private $cover_photo;
    private $access_token;
    private $refresh_token;
    private $bio;
    private $phone;
    private $username;
    private $messageCount = 0;
    private $daily_productCount;
    private $created_at;
    private $updated_at;

    private $city_name;
    private $country_name;
    private $country_image_url;

    private $referred_by;
    private $ambassador_points = 0;
    private $productCount = 0;

    private ?int $roleId = null;
    private ?string $roleName = null;

    private array $roleNames = [];
    public function setRoleNames(array $names): void
    {
        $this->roleNames = $names;
    }
    public function getRoleNames(): array
    {
        return $this->roleNames;
    }
    public function hasRole(string $name): bool
    {
        return in_array($name, $this->roleNames, true) || $this->getRoleName() === $name;
    }

    public function __construct($fullname, $email, $photo = null, $password = null, $role = null, $status = null, $verified_email = 0, $cover_photo = null, $access_token = null, $refresh_token = null, $bio = null, $phone = null, $username = null)
    {
        $this->fullname = $fullname;
        $this->email = $email;
        $this->photo = $photo;
        $this->password = $password;
        $this->role = $role;
        $this->status = $status;
        $this->verified_email = $verified_email;
        $this->cover_photo = $cover_photo;
        $this->access_token = $access_token;
        $this->refresh_token = $refresh_token;
        $this->bio = $bio;
        $this->phone = $phone;
        $this->username = $username;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getFullname()
    {
        return $this->fullname;
    }

    public function setFullname($fullname)
    {
        $this->fullname = $fullname;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getPhoto()
    {
        return $this->photo;
    }

    public function setPhoto($photo)
    {
        $this->photo = $photo;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function setRole($role)
    {
        $this->role = $role;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getVerifiedEmail()
    {
        return $this->verified_email;
    }

    public function setVerifiedEmail($verified_email)
    {
        $this->verified_email = $verified_email;
    }

    public function getCoverPhoto()
    {
        return $this->cover_photo;
    }

    public function setCoverPhoto($cover_photo)
    {
        $this->cover_photo = $cover_photo;
    }

    public function getAccessToken()
    {
        return $this->access_token;
    }

    public function setAccessToken($accessToken)
    {
        $this->access_token = $accessToken;
    }

    public function getRefreshToken()
    {
        return $this->refresh_token;
    }

    public function setRefreshToken($refreshToken)
    {
        $this->refresh_token = $refreshToken;
    }

    public function getBio()
    {
        return $this->bio;
    }

    public function setBio($bio)
    {
        $this->bio = $bio;
    }

    public function getPhone()
    {
        return $this->phone;
    }

    public function setPhone($phone)
    {
        $this->phone = $phone;
    }

    public function getUsername()
    {
        return $this->username;
    }
    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getMessageCount()
    {
        return $this->messageCount;
    }

    public function setMessageCount($messageCount)
    {
        $this->messageCount = $messageCount;
    }

    public function getDailyProductCount()
    {
        return $this->daily_productCount;
    }
    public function setDailyProductCount($daily_productCount)
    {
        $this->daily_productCount = $daily_productCount;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreateAt($created_at)
    {
        $this->created_at = $created_at;
    }

    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    public function setUpdateAt($updated_at)
    {
        $this->updated_at = $updated_at;
    }

    public function setCityName($city_name)
    {
        $this->city_name = $city_name;
    }

    public function setCountryName($country_name)
    {
        $this->country_name = $country_name;
    }

    public function setCountryImageUrl($country_image_url)
    {
        $this->country_image_url = $country_image_url;
    }

    public function getCityName()
    {
        return $this->city_name;
    }

    public function getCountryName()
    {
        return $this->country_name;
    }

    public function getCountryImageUrl()
    {
        return $this->country_image_url;
    }

    public function getReferredBy()
    {
        return $this->referred_by;
    }
    public function setReferredBy($referred_by)
    {
        $this->referred_by = $referred_by;
    }

    public function getAmbassadorPoints(): int
    {
        return $this->ambassador_points ?? 0;
    }

    public function setAmbassadorPoints(int $points): void
    {
        $this->ambassador_points = $points;
    }

    public function setProductCount(int $n): void
    {
        $this->productCount = $n;
    }
    public function getProductCount(): int
    {
        return (int)$this->productCount;
    }

    public function setRoleId(int $roleId): void
    {
        $this->roleId = $roleId;
    }
    public function getRoleId(): ?int
    {
        return $this->roleId;
    }

    public function setRoleName(string $roleName): void
    {
        $this->roleName = $roleName;
    }
    public function getRoleName(): ?string
    {
        return $this->roleName;
    }
}
