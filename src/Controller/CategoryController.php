<?php

namespace App\Controller;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CategoryController extends AbstractController
{
    #[Route('/categories', name: 'get_all_categories', methods: ['GET', 'HEAD'])]
    public function list(ManagerRegistry $doctrine, SerializerInterface $serializer): JsonResponse
    {
        try {
            $categories = $doctrine->getRepository(Category::class)->findAll();

            //Realiza la serialización de los objetos de la colección $categories en formato JSON
            $categoriesAsJson = $serializer->serialize($categories, 'json', ['groups' => 'category']);

            // Convierte la cadena JSON a un array asociativo
            $categoriesArray = json_decode($categoriesAsJson, true);

            // Crea el array de respuesta que contiene el campo 'success' => true junto con la lista de categorías
            $responseArray = [
                'success' => true,
                'data' => $categoriesArray
            ];

            // Convierte el array de respuesta a una cadena JSON
            $responseJson = json_encode($responseArray, JSON_UNESCAPED_UNICODE);

            return new JsonResponse($responseJson, Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al mostrar las categorías: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/category/{id}', name: 'get_category_by_id', methods: ['GET'])]
    public function show(ManagerRegistry $doctrine, SerializerInterface $serializer, int $id): Response
    {
        try {
            $category = $doctrine->getRepository(Category::class)->find($id);

            if (!$category) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No se ha encontrado ninguna categoría con el id ' . $id
                ], Response::HTTP_NOT_FOUND);
            }

            $categoriesAsJson = $serializer->serialize($category, 'json', ['groups' => 'category']);

            $categoriesArray = json_decode($categoriesAsJson, true);

            $responseArray = [
                'success' => true,
                'data' => $categoriesArray
            ];

            $responseJson = json_encode($responseArray, JSON_UNESCAPED_UNICODE);

            return new JsonResponse($responseJson, Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al mostrar la categoría: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/new-category', name: 'create_category', methods: ['POST'])]
    public function createCategory(Request $request, SerializerInterface $serializer, ValidatorInterface $validator, EntityManagerInterface $entityManager): JsonResponse
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
                'description' => [
                    new Assert\NotBlank([
                        'message' => 'El campo descripción no puede estar vacío.'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^(?!\s)(?! *$)[A-Za-z0-9 ]{10,255}$/',
                        'match' => true,
                        'message' => 'Formato de descripción no válido.'
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

            $category = $serializer->deserialize($jsonData, Category::class, 'json');
            $errors = $validator->validate($category);

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

            $entityManager->persist($category);
            $entityManager->flush();

            $entityManager->getConnection()->commit();

            $data =  [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription()
            ];

            return new JsonResponse([
                'success' => true,
                'message' => 'Categoría creada satisfactoriamente',
                'data' => $data
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            $entityManager->getConnection()->rollBack();
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al crear la categoría: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/category/{id}', name: 'modify_category', methods: ['PUT', 'PATCH'])]
    public function update(ManagerRegistry $doctrine, Request $request, int $id, SerializerInterface $serializer): JsonResponse
    {
        try {
            $entityManager = $doctrine->getManager();

            $category = $entityManager->getRepository(Category::class)->find($id);

            if (!$category) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No se ha encontrado ninguna categoría con el id ' . $id
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            // Actualiza las propiedades manualmente apra evitar el error de referencia circular
            $category->setName($data['name']);
            $category->setDescription($data['description']);

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Modificada la categoría con id ' . $id
                // 'data' => $serializedData
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al modificar la categoría: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    #[Route('/category/{id}', name: 'delete_category', methods: ['DELETE'])]
    public function delete(ManagerRegistry $doctrine, int $id, SerializerInterface $serializer): JsonResponse
    {
        try {
            $entityManager = $doctrine->getManager();
            $categoryRepository = $entityManager->getRepository(Category::class);

            $category = $categoryRepository->find($id);

            if (!$category) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'No se ha encontrado ninguna categoría con el id ' . $id
                ], Response::HTTP_NOT_FOUND);
            }

            $entityManager->remove($category);
            $entityManager->flush();

            $responseData = $serializer->serialize([
                'success' => true,
                'message' => 'Eliminada categoría con id ' . $id
            ], 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        } catch (\Throwable $th) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al eliminar la categoría: ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
