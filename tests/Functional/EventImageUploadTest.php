<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Entity\Event;
use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
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

        // Step 1: Login
        $crawler = $client->request('GET', '/en/login');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Login')->form([
            '_username' => self::ADMIN_EMAIL,
            '_password' => self::ADMIN_PASSWORD,
        ]);
        $client->submit($form);
        $this->assertResponseRedirects();
        $client->followRedirect();

        // Verify user is logged in
        $user = $client->getContainer()->get('security.token_storage')->getToken()?->getUser();
        $this->assertInstanceOf(User::class, $user);

        // Step 2: Get an event from the database
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $event = $em->getRepository(Event::class)->findOneBy([]);
        $this->assertNotNull($event, 'At least one event should exist in fixtures');
        $eventId = $event->getId();

        // Count existing images for this event before upload
        $imageCountBefore = $em->getRepository(Image::class)->count([
            'event' => $event,
            'type' => ImageType::EventUpload,
        ]);

        // Step 3: Navigate to event upload page
        $crawler = $client->request('GET', '/en/event/upload/' . $eventId);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form', 'Upload form should exist');

        // Step 4: Upload an image - create a small test image
        $tempFile = sys_get_temp_dir() . '/test_upload_' . uniqid() . '.jpg';
        $img = imagecreatetruecolor(100, 100);
        $color = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $color);
        imagejpeg($img, $tempFile, 90);
        imagedestroy($img);
        $this->assertFileExists($tempFile, 'Test image file should be created');

        // Get CSRF token from the form
        $csrfToken = $crawler->filter('input[name="event_upload[_token]"]')->attr('value');

        // Submit form with file upload using multipart
        $client->request(
            'POST',
            '/en/event/upload/' . $eventId,
            ['event_upload' => ['_token' => $csrfToken]],
            ['event_upload' => ['files' => [new UploadedFile($tempFile, 'test_image.jpg', 'image/jpeg', null, true)]]],
        );

        // Step 5: Verify redirect to event details page
        $this->assertResponseRedirects('/en/event/' . $eventId);
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Step 6: Verify new image entry in database
        $em->clear(); // Clear entity manager to get fresh data
        $imageCountAfter = $em->getRepository(Image::class)->count([
            'event' => $em->getRepository(Event::class)->find($eventId),
            'type' => ImageType::EventUpload,
        ]);

        $this->assertGreaterThan(
            $imageCountBefore,
            $imageCountAfter,
            'A new image should be created in the database after upload'
        );

        // Step 7: Verify the image is linked to the event
        $newImage = $em->getRepository(Image::class)->findOneBy([
            'event' => $em->getRepository(Event::class)->find($eventId),
            'type' => ImageType::EventUpload,
            'uploader' => $user,
        ]);
        $this->assertNotNull($newImage, 'The uploaded image should be linked to the event');
        $this->assertSame(ImageType::EventUpload, $newImage->getType());
        $this->assertNotNull($newImage->getHash(), 'Image should have a hash');
        $this->assertNotNull($newImage->getCreatedAt(), 'Image should have a creation date');

        // Cleanup temp file
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}
