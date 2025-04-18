<?php

namespace App\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

class UserRegistrationDTO
{

    #[Assert\NotBlank(message: "Username is required")]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: "Username must be at least {{ limit }} characters long",
        maxMessage: "Username cannot be longer than {{ limit }} characters"
    )]
    private $username;

    #[Assert\NotBlank(message: "Email is required")]
    #[Assert\Email(message: "The email '{{ value }}' is not a valid email.")]
    #[Assert\Length(max: 255, maxMessage: "Email cannot be longer than {{ limit }} characters")]
    private string $email;

    #[Assert\NotBlank(message: "Password is required")]
    #[Assert\Length(
        min: 8,
        minMessage: "Password must be at least {{ limit }} characters long"
    )]
    private string $password;

    #[Assert\Length(
        max: 100,
        maxMessage: "First name cannot be longer than {{ limit }} characters"
    )]
    private ?string $firstName = null;

    #[Assert\Length(
        max: 100,
        maxMessage: "Last name cannot be longer than {{ limit }} characters"
    )]
    private ?string $lastName = null;

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @param string|null $firstName
     */
    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @param string|null $lastName
     */
    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

}