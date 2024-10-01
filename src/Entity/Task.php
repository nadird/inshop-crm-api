<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Controller\TaskDeadlineAction;
use App\Interfaces\ClientInterface;
use App\Repository\TaskRepository;
use App\Traits\Blameable;
use App\Traits\IsActive;
use App\Traits\Timestampable;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    collectionOperations: [
        'get' => ['security' => "is_granted('ROLE_TASK_LIST')"],
        'post' => ['security' => "is_granted('ROLE_TASK_CREATE')"],
        'deadline' => [
            'security' => "is_granted('ROLE_TASK_DEADLINE')",
            'method' => 'GET',
            'path' => '/tasks/deadline',
            'controller' => TaskDeadlineAction::class,
            'defaults' => ['_api_receive' => false],
            'normalization_context' => ['groups' => ["task_read", "read", "is_active_read"]]
        ],
    ],
    itemOperations: [
        'get' => ['security' => "is_granted('ROLE_TASK_SHOW')"],
        'put' => ['security' => "is_granted('ROLE_TASK_UPDATE')"],
        'delete' => ['security' => "is_granted('ROLE_TASK_DELETE')"],
    ],
    attributes: [
        'order' => ['id' => "DESC"],
        'normalization_context' => ['groups' => ["task_read", "read", "is_active_read"]],
        'denormalization_context' => ['groups' => ["task_write", "is_active_write"]],
    ]
)]
#[ApiFilter(
    DateFilter::class,
    properties: [
        "deadline",
        "createdAt",
        "updatedAt",
    ]
)]
#[ApiFilter(
    SearchFilter::class,
    properties: [
        "id" => "exact",
        "status.id" => "exact",
        "project.name" => "ipartial",
        "project.client.name" => "ipartial",
        "assignee.name" => "ipartial",
        "name" => "ipartial"
    ]
)]
#[ApiFilter(
    OrderFilter::class,
    properties: [
        "id",
        "status.id",
        "project.name",
        "project.client.name",
        "assignee.name",
        "name",
        "timeEstimated",
        "timeSpent",
        "deadline",
        "createdAt",
        "updatedAt"
    ]
)]
#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task implements ClientInterface
{
    use Timestampable;
    use Blameable;
    use IsActive;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups([
        "task_read",
        "user_read",
        "project_read",
        "project_write",
    ])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Groups([
        "task_read",
        "task_write",
        "user_read",
        "project_read",
        "project_write",
    ])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups([
        "task_read",
        "task_write",
        "user_read",
        "project_read",
    ])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    #[Groups([
        "task_read",
        "task_write",
        "user_read",
    ])]
    private Project $project;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    #[Groups([
        "task_read",
        "task_write",
        "user_read",
        "project_read",
        "project_write",
    ])]
    private DateTime $deadline;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tasks')]
    #[Groups([
        "task_read",
        "task_write",
        "project_read",
    ])]
    private ?User $assignee = null;

    #[ORM\ManyToOne(targetEntity: TaskStatus::class)]
    #[Assert\NotBlank]
    #[Groups([
        "task_read",
        "task_write",
        "user_read",
        "project_read",
        "project_write"
    ])]
    private ?TaskStatus $status = null;

    // Estimated time in minutes
    #[ORM\Column(type: 'float', options: ['default' => 0])]
    #[Groups([
        "task_read",
        "task_write",
        "project_read",
        "project_write"
    ])]
    private float $timeEstimated = 0;

    // Spent time in minutes
    #[ORM\Column(type: 'float', options: ['default' => 0])]
    #[Groups([
        "task_read",
        "task_write",
        "project_read",
        "project_write"
    ])]
    private float $timeSpent = 0;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $googleEventId = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getDeadline(): DateTime
    {
        return $this->deadline;
    }

    public function setDeadline(DateTime $deadline): void
    {
        $this->deadline = $deadline;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): self
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getStatus(): ?TaskStatus
    {
        return $this->status;
    }

    public function setStatus(?TaskStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getClient(): Client
    {
        return $this->getProject()->getClient();
    }

    public function getTimeEstimated(): float
    {
        return round($this->timeEstimated / 60, 1);
    }

    public function setTimeEstimated(float $timeEstimated): self
    {
        $this->timeEstimated = (int) ($timeEstimated * 60);

        return $this;
    }

    public function getTimeSpent(): float
    {
        return round($this->timeSpent / 60, 1);
    }

    public function setTimeSpent(float $timeSpent): self
    {
        $this->timeSpent = (int) ($timeSpent * 60);

        return $this;
    }

    public function getGoogleEventId(): ?string
    {
        return $this->googleEventId;
    }

    public function setGoogleEventId(?string $googleEventId): self
    {
        $this->googleEventId = $googleEventId;

        return $this;
    }
}
