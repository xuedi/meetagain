<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Event;
use App\Entity\User;
use App\Tests\Unit\Entity\Stubs\CommentStub;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CommentTest extends TestCase
{
    private CommentStub $comment;

    protected function setUp(): void
    {
        $this->comment = new CommentStub();
        $this->comment->setId(1);
    }

    public function testIdGetter(): void
    {
        $this->assertEquals(1, $this->comment->getId());
    }

    public function testEventGetterAndSetter(): void
    {
        $event = new Event();
        $this->comment->setEvent($event);

        $this->assertSame($event, $this->comment->getEvent());
    }

    public function testUserGetterAndSetter(): void
    {
        $user = new User();
        $this->comment->setUser($user);

        $this->assertSame($user, $this->comment->getUser());
    }

    public function testCreatedAtGetterAndSetter(): void
    {
        $date = new DateTimeImmutable();
        $this->comment->setCreatedAt($date);

        $this->assertEquals($date, $this->comment->getCreatedAt());
    }

    public function testContentGetterAndSetter(): void
    {
        $content = 'This is a test comment';
        $this->comment->setContent($content);

        $this->assertEquals($content, $this->comment->getContent());
    }
}
