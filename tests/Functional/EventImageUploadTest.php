<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\Event;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EventImageUploadTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'Admin@example.org';
    private const string ADMIN_PASSWORD = '1234';

    public function testUserCanLoginAndUploadImageToEvent(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/login');
        $this->assertResponseIsSuccessful();

        $form = $crawler
            ->selectButton('Login')
            ->form([
                '_username' => self::ADMIN_EMAIL,
                '_password' => self::ADMIN_PASSWORD,
            ]);
        $client->submit($form);
        $this->assertResponseRedirects();
        $client->followRedirect();

        $user = $client->getContainer()->get('security.token_storage')->getToken()?->getUser();
        static::assertInstanceOf(User::class, $user);

        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $event = $em->getRepository(Event::class)->findOneBy([]);
        static::assertNotNull($event, 'At least one event should exist in fixtures');
        $eventId = $event->getId();

        $imageCountBefore = $em->getRepository(Image::class)->count([
            'event' => $event,
            'type' => ImageType::EventUpload,
        ]);

        $crawler = $client->request('GET', '/en/image/event/' . $eventId . '/modal');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form', 'Upload form should exist in modal content');

        $tempFile = sys_get_temp_dir() . '/test_upload_' . uniqid() . '.jpg';
        $img = imagecreatetruecolor(100, 100);
        $color = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $color);
        imagejpeg($img, $tempFile, 90);
        imagedestroy($img);
        static::assertFileExists($tempFile, 'Test image file should be created');

        $csrfToken = $crawler->filter('input[name="event_upload[_token]"]')->attr('value');

        $client->request(
            'POST',
            '/en/image/event/' . $eventId . '/upload',
            ['event_upload' => ['_token' => $csrfToken]],
            ['event_upload' => ['files' => [new UploadedFile($tempFile, 'test_image.jpg', 'image/jpeg', null, true)]]],
        );

        $this->assertResponseRedirects('/en/event/' . $eventId);
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $em->clear();
        $imageCountAfter = $em->getRepository(Image::class)->count([
            'event' => $em->getRepository(Event::class)->find($eventId),
            'type' => ImageType::EventUpload,
        ]);

        static::assertGreaterThan($imageCountBefore, $imageCountAfter, 'A new image should be created in the database after upload');

        $newImage = $em->getRepository(Image::class)->findOneBy([
            'event' => $em->getRepository(Event::class)->find($eventId),
            'type' => ImageType::EventUpload,
            'uploader' => $user,
        ]);
        static::assertNotNull($newImage, 'The uploaded image should be linked to the event');
        static::assertSame(ImageType::EventUpload, $newImage->getType());
        static::assertNotNull($newImage->getHash(), 'Image should have a hash');
        static::assertNotNull($newImage->getCreatedAt(), 'Image should have a creation date');

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}
