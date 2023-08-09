<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Note;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\EntityManagerInterface;

class NoteController extends AbstractController
{
    #[Route('/notes', name: 'get_all_notes', methods: ['GET', 'HEAD'])]
    public function list(ManagerRegistry $doctrine, SerializerInterface $serializer): JsonResponse
    {
        try {
            $notes = $doctrine->getRepository(Note::class)->findAll();

            // Realiza la serialización de los objetos de la colección $notes en formato JSON
            $notesAsJson = $serializer->serialize($notes, 'json', ['groups' => 'note_user']);

            // Convierte la cadena JSON a un array asociativo
            $notesArray = json_decode($notesAsJson, true);

            // Crea el array de respuesta que contiene el campo 'success' => true junto con la lista de notas
            $responseArray = [
                'success' => true,
                'data' => $notesArray
            ];

            // Convierte el array de respuesta a una cadena JSON
            $responseJson = json_encode($responseArray, JSON_UNESCAPED_UNICODE);

            return new JsonResponse($responseJson, Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al mostrar las notas: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/note/{id}', name: 'get_note_by_id', methods: ['GET'])]
    public function show(ManagerRegistry $doctrine, SerializerInterface $serializer, int $id): Response
    {
        try {
            $note = $doctrine->getRepository(Note::class)->find($id);

            if (!$note) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No se ha encontrado ninguna nota con el id ' . $id
                ], Response::HTTP_NOT_FOUND);
            }

            $notesAsJson = $serializer->serialize($note, 'json', ['groups' => 'note_user']);

            $notesArray = json_decode($notesAsJson, true);

            $responseArray = [
                'success' => true,
                'data' => $notesArray
            ];

            $responseJson = json_encode($responseArray, JSON_UNESCAPED_UNICODE);

            return new JsonResponse($responseJson, Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al mostrar la nota: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/new-note', name: 'create_note', methods: ['POST'])]
    public function createNote(Request $request, ValidatorInterface $validator, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $jsonData = $request->getContent();
            $data = json_decode($jsonData, true);

            $constraints = new Assert\Collection([
                'description' => [
                    new Assert\NotBlank([
                        'message' => 'El campo descripción no puede estar vacío.'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^(?!\s)(?! *$)[A-Za-z0-9 ]{10,255}$/',
                        'match' => true,
                        'message' => 'Formato de descripción no válido.'
                    ])
                ],
                'user' => [
                    new Assert\NotBlank([
                        'message' => 'El campo usuario no puede estar vacío.'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^\d+$/',
                        'match' => true,
                        'message' => 'Formato de usuario no válido.'
                    ])
                ],
                'categories' => [
                    new Assert\NotBlank([
                        'message' => 'El campo categoría no puede estar vacío.'
                    ])
                ]
            ]);

            $violations = $validator->validate($data, $constraints);
            if (count($violations) > 0) {
                $errorMessages = [];
                foreach ($violations as $violation) {
                    $errorMessages[$violation->getPropertyPath()] = $violation->getMessage();
                }
                return new JsonResponse([
                    'success' => true,
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            $entityManager->getConnection()->beginTransaction();

            $user = $entityManager->getRepository(User::class)->find($data['user']);

            if (!$user) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'El usuario con Id ' . $data['user'] . ' no existe.'
                ], Response::HTTP_NOT_FOUND);
            }

            $note = new Note();
            $note->setDescription($data['description']);
            $note->setUser($user);

            if (isset($data['categories']) && is_array($data['categories'])) {
                foreach ($data['categories'] as $categoryId) {
                    $category = $entityManager->getRepository(Category::class)->find($categoryId);
                    if ($category) {
                        $note->addCategory($category);
                    }
                }
            }

            $entityManager->persist($note);
            $entityManager->flush();
            $entityManager->getConnection()->commit();

            $data =  [
                'id' => $note->getId(),
                'description' => $note->getDescription(),
                'user' => $note->getUser()->getId()
            ];

            return new JsonResponse([
                'success' => true,
                'message' => 'Nota creada satisfactoriamente',
                'data' => $data
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            $entityManager->getConnection()->rollBack();
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al crear la nota: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/note/{id}', name: 'modify_note', methods: ['PUT', 'PATCH'])]
    public function updateNote(Request $request, EntityManagerInterface $entityManager, int $id, SerializerInterface $serializer): JsonResponse
    {
        try {
            $note = $entityManager->getRepository(Note::class)->find($id);

            if (!$note) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No se ha encontrado ninguna nota con el id ' . $id,
                ], JsonResponse::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            // Valida si el campo user está presente en el body
            if (!isset($data['user'])) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'El campo usuario es obligatorio en la solicitud.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            $note = $serializer->deserialize($request->getContent(), Note::class, 'json', ['object_to_populate' => $note]);

            $newUserId = $data['user'];

            $newUser = $entityManager->getRepository(User::class)->find($newUserId);

            if (!$newUser) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'El usuario con Id ' . $newUserId . ' no existe.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }

            $note->setUser($newUser);

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Modificada la nota con id ' . $id
            ], JsonResponse::HTTP_OK);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al modificar la nota: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/note/{id}', name: 'delete_note', methods: ['DELETE'])]
    public function delete(ManagerRegistry $doctrine, int $id, SerializerInterface $serializer): JsonResponse
    {
        try {
            $entityManager = $doctrine->getManager();
            $noteRepository = $entityManager->getRepository(Note::class);

            $note = $noteRepository->find($id);

            if (!$note) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No se ha encontrado ninguna nota con el id ' . $id
                ], Response::HTTP_NOT_FOUND);
            }

            $entityManager->remove($note);
            $entityManager->flush();

            $responseData = $serializer->serialize([
                'success' => true,
                'message' => 'Eliminada nota con id ' . $id
            ], 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al eliminar la nota: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
