<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\SerializerInterface;

class UserController extends AbstractController
{
    #[Route('/users', name: 'get_all_users', methods: ['GET', 'HEAD'])]
    public function list(ManagerRegistry $doctrine, SerializerInterface $serializer): JsonResponse
    {
        try {
            $users = $doctrine->getRepository(User::class)->findAll();

            //Realiza la serialización de los objetos de la colección $users en formato JSON
            $usersAsJson = $serializer->serialize($users, 'json', ['groups' => 'user']);

            // Convierte la cadena JSON a un array asociativo
            $usersArray = json_decode($usersAsJson, true);

            // Crea el array de respuesta que contiene el campo 'success' => true junto con la lista de usuarios
            $responseArray = [
                'success' => true,
                'data' => $usersArray
            ];

            // Convierte el array de respuesta a una cadena JSON
            $responseJson = json_encode($responseArray, JSON_UNESCAPED_UNICODE);

            return new JsonResponse($responseJson, Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al mostrar los usuarios: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/user/{id}', name: 'get_user_by_id', methods: ['GET'])]
    public function show(ManagerRegistry $doctrine, SerializerInterface $serializer, int $id): Response
    {
        try {
            $user = $doctrine->getRepository(User::class)->find($id);

            if (!$user) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No se ha encontrado ningún usuario con el id ' . $id
                ], Response::HTTP_NOT_FOUND);
            }

            $usersAsJson = $serializer->serialize($user, 'json', ['groups' => 'user']);

            $usersArray = json_decode($usersAsJson, true);

            $responseArray = [
                'success' => true,
                'data' => $usersArray
            ];

            $responseJson = json_encode($responseArray, JSON_UNESCAPED_UNICODE);

            return new JsonResponse($responseJson, Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al mostrar el usuario: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/new-user', name: 'register_user', methods: ['POST'])]
    public function createUser(Request $request, SerializerInterface $serializer, ValidatorInterface $validator, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $jsonData = $request->getContent();
            $data = json_decode($jsonData, true);

            $constraints = new Assert\Collection([
                'name' => [
                    new Assert\NotBlank([
                        'message' => 'El campo nombre no puede estar vacío.'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^(?=(?:[^A-Za-z]*[A-Za-z]){2})(?!(?:[^A-Za-z]*[A-Za-z]){21})[A-Za-z ]{2,20}$/',
                        'match' => true,
                        'message' => 'Formato de nombre no válido.'
                    ])
                ],
                'surname' => [
                    new Assert\NotBlank([
                        'message' => 'El campo apellidos no puede estar vacío.'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^(?=(?:[^A-Za-z]*[A-Za-z]){2})(?!(?:[^A-Za-z]*[A-Za-z]){21})[A-Za-z ]{2,20}$/',
                        'match' => true,
                        'message' => 'Formato de apellidos no válido.'
                    ])
                ],
                'email' => [
                    new Assert\NotBlank([
                        'message' => 'El campo email no puede estar vacío.'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                        'match' => true,
                        'message' => 'Formato de email no válido.'
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

            $user = $serializer->deserialize($jsonData, User::class, 'json');
            $errors = $validator->validate($user);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse([
                    'success' => true,
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            $entityManager->getConnection()->beginTransaction();

            $entityManager->persist($user);
            $entityManager->flush();

            $entityManager->getConnection()->commit();

            $data =  [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'email' => $user->getEmail()
            ];

            return new JsonResponse([
                'success' => true,
                'message' => 'Usuario creado satisfactoriamente',
                'data' => $data
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            $entityManager->getConnection()->rollBack();
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al registrar el usuario: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/user/{id}', name: 'modify_user', methods: ['PUT', 'PATCH'])]
    public function update(ManagerRegistry $doctrine, Request $request, int $id, SerializerInterface $serializer): JsonResponse
    {
        try {
            $entityManager = $doctrine->getManager();

            $user = $entityManager->getRepository(User::class)->find($id);

            if (!$user) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No se ha encontrado ningún usuario con el id ' . $id
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            $serializer->deserialize(json_encode($data), User::class, 'json', ['object_to_populate' => $user]);

            $entityManager->flush();

            $responseData = $serializer->serialize($user, 'json');

            return new JsonResponse([
                'success' => true,
                'message' => 'Modificado el usuario con id ' . $id,
                'data' => json_decode($responseData, true)
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al modificar el usuario: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/user/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function delete(ManagerRegistry $doctrine, int $id, SerializerInterface $serializer): JsonResponse
    {
        try {
            $entityManager = $doctrine->getManager();
            $userRepository = $entityManager->getRepository(User::class);

            $user = $userRepository->find($id);

            if (!$user) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No se ha encontrado ningún usuario con el id ' . $id
                ], Response::HTTP_NOT_FOUND);
            }

            $entityManager->remove($user);
            $entityManager->flush();

            $responseData = $serializer->serialize([
                'success' => true,
                'message' => 'Eliminado usuario con id ' . $id
            ], 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al eliminar el usuario: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
