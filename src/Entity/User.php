<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Controller\DashboardAction;
use App\Controller\User\UserPostCollectionController;
use App\Controller\User\UserPutItemController;
use App\Repository\UserRepository;
use App\Traits\Blameable;
use App\Traits\IsActive;
use App\Traits\Timestampable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    collectionOperations: [
        'get' => [
            'security' => "is_granted('ROLE_USER_LIST')",
            'normalization_context' => ['groups' => ["user_read_collection", "read", "is_active_read"]],
        ],
        'post' => [
            'controller' => UserPostCollectionController::class,
            'security' => "is_granted('ROLE_USER_CREATE')"
        ],
        'dashboard' => [
            'security' => "is_granted('ROLE_USER_DASHBOARD')",
            'method' => 'GET',
            'path' => '/users/dashboard',
            'controller' => DashboardAction::class,
            'defaults' => ['_api_receive' => false]
        ],
    ],
    itemOperations: [
        'get' => ['security' => "is_granted('ROLE_USER_SHOW')"],
        'put' => [
            'controller' => UserPutItemController::class,
            'security' => "is_granted('ROLE_USER_UPDATE')"
        ],
        'delete' => ['security' => "is_granted('ROLE_USER_DELETE')"],
    ],
    attributes: [
        'order' => ['id' => "DESC"],
        'normalization_context' => ['groups' => ["user_read", "read", "is_active_read"]],
        'denormalization_context' => ['groups' => ["user_write", "is_active_write"]],
    ]
)]
#[ApiFilter(
    DateFilter::class,
    properties: [
        "createdAt",
        "updatedAt",
    ]
)]
#[ApiFilter(
    SearchFilter::class,
    properties: [
        "id" => "exact",
        "name" => "ipartial",
        "email" => "ipartial",
        "groups.name" => "ipartial"
    ]
)]
#[ApiFilter(
    OrderFilter::class,
    properties: [
        "id",
        "name",
        "email",
        "groups.name",
        "createdAt",
        "updatedAt"
    ]
)]
#[UniqueEntity(fields: ['username'])]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use Timestampable;
    use Blameable;
    use IsActive;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        "user_read",
        "user_read_collection",
        "task_read",
        "client_read",
        "project_read",
        "task_write",
    ])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups([
        "user_read",
        "user_write",
        "task_read",
        "client_read",
    ])]
    private string $username;

    #[ORM\Column(type: 'string', length: 64)]
    #[Assert\NotBlank]
    private string $password;

    #[Groups([
        "user_write",
    ])]
    private ?string $plainPassword = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Groups([
        "user_read",
        "user_read_collection",
        "user_write",
        "task_read",
        "client_read",
        "project_read",
    ])]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Groups([
        "user_read",
        "user_read_collection",
        "user_write",
        "task_read",
        "client_read",
    ])]
    private string $email;

    #[ORM\OneToMany(mappedBy: 'assignee', targetEntity: Task::class)]
    #[ORM\OrderBy(['id' => 'DESC'])]
    private Collection $tasks;

    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[Groups([
        "user_read",
        "user_read_collection",
        "user_write",
    ])]
    private Collection $groups;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[Assert\NotNull]
    #[Groups([
        "user_read",
        "user_read_collection",
        "user_write",
    ])]
    private ?Language $language = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isGoogleSyncEnabled = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $googleAccessToken = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $googleCalendars = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $googleCalendarId = null;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->groups = new ArrayCollection();
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        $roles[] = ['ROLE_USER'];

        foreach ($this->getGroups() as $group) {
            $roles[] = $group->getRolesArray();
        }

        return array_merge(...$roles);
    }

    public function eraseCredentials(): void
    {
    }

    public function __serialize(): array
    {
        return array(
            $this->id,
            $this->username,
            $this->password,
        );
    }

    public function __unserialize($serialized): void
    {
        [
            $this->id,
            $this->username,
            $this->password,
        ] = unserialize($serialized, ['allowed_classes' => false]);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        $this->email = $username;

        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks[] = $task;
            $task->setAssignee($this);
        }

        return $this;
    }

    public function removeTask(Task $task): self
    {
        if ($this->tasks->contains($task)) {
            $this->tasks->removeElement($task);
            // set the owning side to null (unless already changed)
            if ($task->getAssignee() === $this) {
                $task->setAssignee(null);
            }
        }

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(Group $group): self
    {
        if (!$this->groups->contains($group)) {
            $this->groups[] = $group;
        }

        return $this;
    }

    public function removeGroup(Group $group): self
    {
        if ($this->groups->contains($group)) {
            $this->groups->removeElement($group);
        }

        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getIsGoogleSyncEnabled(): ?bool
    {
        return $this->isGoogleSyncEnabled;
    }

    public function setIsGoogleSyncEnabled(?bool $isGoogleSyncEnabled): self
    {
        $this->isGoogleSyncEnabled = $isGoogleSyncEnabled;

        return $this;
    }

    public function getGoogleAccessToken(): ?string
    {
        return $this->googleAccessToken;
    }

    public function setGoogleAccessToken(?string $googleAccessToken): self
    {
        $this->googleAccessToken = $googleAccessToken;

        return $this;
    }

    public function getGoogleCalendars(): ?string
    {
        return $this->googleCalendars;
    }

    public function setGoogleCalendars(?string $googleCalendars): self
    {
        $this->googleCalendars = $googleCalendars;

        return $this;
    }

    public function getGoogleCalendarId(): ?string
    {
        return $this->googleCalendarId;
    }

    public function setGoogleCalendarId(?string $googleCalendarId): self
    {
        $this->googleCalendarId = $googleCalendarId;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string)$this->getId();
    }

    public function isIsGoogleSyncEnabled(): ?bool
    {
        return $this->isGoogleSyncEnabled;
    }
}
