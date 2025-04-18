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
    private string $username;

    #[Assert\NotBlank(message: "Email is required")]
    #[Assert\Email(message: "The email '{{ value }}' is not a valid email.")]
    #[Assert\Length(max: 255, maxMessage: "Email cannot be longer than {{ limit }} characters")]
    private string $email;

    #[Assert\NotBlank(message: "Password is required")]
    #[Assert\Length(
        min: 8,
        minMessage: "Password must be at least {{ limit }} characters long"
    )]
    #[Assert\Regex(
        pattern: "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/",
        message: "Password must contain at least one uppercase letter, one lowercase letter, and one number"
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
     * @return self
     */
    public function setUsername(string $username): self
    {
        $this->username = trim($username);
        return $this;
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
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->email = trim($email);
        return $this;
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
     * @return self
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
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
     * @return self
     */
    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName !== null ? trim($firstName) : null;
        return $this;
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
     * @return self
     */
    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName !== null ? trim($lastName) : null;
        return $this;
    }
}