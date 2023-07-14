<?php
// api/src/Controller/CreateMediaObjectAction.php
namespace App\Controller;
use App\Entity\MediaObject;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsController]
final class CreateMediaObjectAction extends AbstractController
{
    private $client;
    private $entityManager;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
    }

    public function __invoke(Request $request): MediaObject
    {
        $uploadedFiles = $request->files->all();
        $batchId = $request->request->get('batchId');

        if (!$uploadedFiles) {
            throw new BadRequestHttpException('"file" is required');
        }

        foreach ($uploadedFiles as $uploadedFile) {
            $mediaObject = new MediaObject();
            $mediaObject->file = $uploadedFile;
            $mediaObject->batchId = $batchId;

            $this->entityManager->persist($mediaObject);
        }
        $this->entityManager->flush();

        // After saving the file, you can query the database to get the files with the latest batchId
        $repository = $this->entityManager->getRepository(MediaObject::class);

        // find the files with the latest batchId
        $latestFiles = $repository->findBy(['batchId' => $batchId]);

        // Send the files to your Python microservice
        $pdfPaths = array_map(function ($mediaObject) {
            return $this->getParameter('kernel.project_dir') . '/public/media/' . $mediaObject->getFilePath();
        }, $latestFiles);

        $data = [
            "pdf_paths" => $pdfPaths,
        ];

        $response = $this->client->request('POST', 'http://127.0.0.1:5000/run-script', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data)
        ]);

        // Handle the response from the Python microservice
        if ($response->getStatusCode() == 200) {
            // The request was successful
            $content = $response->getContent();
            $contentArray = json_decode($content, true);
            $generatedText = $contentArray['generated_text'] ?? null;
            // Set the generated text in the MediaObject
            foreach ($latestFiles as $mediaObject) {
                $mediaObject->setGeneratedText($generatedText);
            }
        } else {
            // The request failed
        }

        return $mediaObject;
    }
}
