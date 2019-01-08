<?php

namespace Opensoft\StorageBundle\Controller;

use Doctrine\ORM\EntityManager;
use Knp\Component\Pager\PaginatorInterface;
use Lexik\Bundle\FormFilterBundle\Filter\FilterBuilderUpdaterInterface;
use Opensoft\StorageBundle\Entity\StorageFile;
use Opensoft\StorageBundle\Entity\Storage;
use Opensoft\StorageBundle\Entity\StoragePolicy;
use Opensoft\StorageBundle\Storage\AdapterResolver;
use Opensoft\StorageBundle\Form\Type\StoragePolicyFormType;
use Opensoft\StorageBundle\Form\Type\StorageType;
use Opensoft\StorageBundle\Form\Type\StoredFilesFilterType;
use Opensoft\StorageBundle\Storage\StorageFileTypeProviderInterface;
use Opensoft\StorageBundle\Storage\StorageManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @author Richard Fullmer <richard.fullmer@opensoftdev.com>
 */
class StorageController extends AbstractController
{
    private const FILES_PER_PAGE = 30;

    /**
     * @var StorageManagerInterface
     */
    private $storageManager;

    /**
     * @var PaginatorInterface
     */
    private $paginator;

    /**
     * @var StorageFileTypeProviderInterface
     */
    private $storageFileTypeProvider;

    /**
     * @var AdapterResolver
     */
    private $adapterResolver;

    /**
     * @param PaginatorInterface $paginator
     * @param StorageFileTypeProviderInterface $storageFileTypeProvider
     * @param AdapterResolver $adapterResolver
     * @param StorageManagerInterface $storageManager
     */
    public function __construct(
        PaginatorInterface $paginator,
        StorageFileTypeProviderInterface $storageFileTypeProvider,
        AdapterResolver $adapterResolver,
        StorageManagerInterface $storageManager
    ) {
        $this->paginator = $paginator;
        $this->storageFileTypeProvider = $storageFileTypeProvider;
        $this->adapterResolver = $adapterResolver;
        $this->storageManager = $storageManager;
    }

    /**
     * @Route("/admin/storage", name="opensoft_storage_list_storages")
     */
    public function listAction(): Response
    {
        $storages = $this->getDoctrine()->getRepository(Storage::class)->findBy([], ['createdAt' => 'asc']);

        return $this->render('@OpensoftStorage/storage/list.html.twig', [
            'storages' => $storages,
            'adapterResolver' => $this->adapterResolver,
            'storageCounter' => $this->getDoctrine()->getRepository(StorageFile::class)
        ]);
    }

    /**
     * @Route("/admin/storage/search", name="opensoft_storage_search_storage")
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function searchAction(Request $request): RedirectResponse
    {
        $search = $request->get('search');
        if (is_numeric($search)) {
            // Assume we're looking by storage id
            $storageFile = $this->getDoctrine()->getRepository(StorageFile::class)->find($search);
            if (!$storageFile) {
                $this->addFlash('info', sprintf("Could not find storage file with ID '%s'", $search));

                return $this->redirectToRoute('opensoft_storage_list_storages');
            }

            return $this->redirectToRoute('opensoft_storage_show_storage_file', ['id' => $storageFile->getId()]);
        }

        if (is_string($search)) {
            // Assume its a storage key and search for it
            $storageFile = $this->getDoctrine()->getRepository(StorageFile::class)->findOneBy(['key' => $search]);
            if (!$storageFile) {
                $this->addFlash('info', sprintf("Could not find storage file with key '%s'", $search));

                return $this->redirectToRoute('opensoft_storage_list_storages');
            }

            return $this->redirectToRoute('opensoft_storage_show_storage_file', ['id' => $storageFile->getId()]);
        }

        $this->addFlash('info', sprintf("Could not find storage file with search term '%s'", $search));

        return $this->redirectToRoute('opensoft_storage_list_storages');
    }

    /**
     * @Route("/admin/storage/create", name="opensoft_storage_create_storage")
     *
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function createAction(Request $request): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_STORAGE_MANAGER')) {
            throw new AccessDeniedException();
        }

        return $this->handleFormRequest($request, new Storage());
    }

    /**
     * @Route("/admin/storage/{id}", name="opensoft_storage_show_storage", requirements={"id" = "\d+"})
     *
     * @param Storage $storage
     * @param Request $request
     * @param FilterBuilderUpdaterInterface $filterBuilderUpdater
     * @return Response
     */
    public function showAction(Storage $storage, Request $request, FilterBuilderUpdaterInterface $filterBuilderUpdater): Response
    {
        $form = $this->createForm(StoredFilesFilterType::class);
        $filterBuilder = $this->getDoctrine()->getRepository(StorageFile::class)->getQueryBuilderForStorage($storage->getId());

        if ($request->query->has($form->getName())) {
            $form->handleRequest($request);
            $filterBuilderUpdater->addFilterConditions($form, $filterBuilder);
        }

        /** @var StorageFile[] $pagination */
        $pagination = $this->paginator->paginate(
            $filterBuilder,
            $request->get('page', 1),
            $request->get('per_page', self::FILES_PER_PAGE)
        );

        $stats = $this->getDoctrine()->getRepository(StorageFile::class)->statsByStorage($storage);
        $adapter = $this->adapterResolver->getConfigurationByClass($storage->getAdapterOptions()['class']);

        return $this->render('@OpensoftStorage/storage/show.html.twig', [
            'storage' => $storage,
            'files' => $pagination,
            'form' => $form->createView(),
            'adapterResolver' => $this->adapterResolver,
            'adapter' => $adapter,
            'fileCount' => $stats['file_count'],
            'fileSize' => $stats['file_size'],
            'fileCountByType' => $this->getDoctrine()->getRepository(StorageFile::class)->statsByStorageGroupedByType($storage),
            'fileTypes' => $this->storageFileTypeProvider->getTypes(),
            'policies' => $this->getDoctrine()->getRepository(StoragePolicy::class)->findAllIndexedByType()
        ]);
    }

    /**
     * @Route("/admin/storage/{id}/edit", name="opensoft_storage_edit_storage", requirements={"id" = "\d+"})
     *
     * @param Storage $storage
     * @param Request $request
     * @return Response|RedirectResponse
     */
    public function editAction(Storage $storage, Request $request): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_STORAGE_MANAGER')) {
            throw new AccessDeniedException();
        }

        return $this->handleFormRequest($request, $storage);
    }

    /**
     * @Route("/admin/storage/{id}/activate", name="opensoft_storage_activate_storage", requirements={"id" = "\d+"})
     *
     * @param Storage $storage
     * @return RedirectResponse
     */
    public function activateAction(Storage $storage): RedirectResponse
    {
        if (!$this->isGranted('ROLE_ADMIN_STORAGE_MANAGER')) {
            throw new AccessDeniedException();
        }

        $storage->setActive(true);

        $this->persist($storage, true);

        return $this->redirectToRoute('opensoft_storage_list_storages');
    }

    /**
     * @Route("/admin/storage/{id}/deactivate", name="opensoft_storage_deactivate_storage", requirements={"id" = "\d+"})
     *
     * @param Storage $storage
     * @return RedirectResponse
     */
    public function deactivateAction(Storage $storage): RedirectResponse
    {
        if (!$this->isGranted('ROLE_ADMIN_STORAGE_MANAGER')) {
            throw new AccessDeniedException();
        }

        $activeStorages = $this->getDoctrine()->getRepository(Storage::class)->findBy(['active' => true]);
        if (count($activeStorages) === 1) {
            $this->addFlash('danger', 'There must be at least one active storage');

            return $this->redirectToRoute('opensoft_storage_list_storages');
        }

        $storage->setActive(false);
        $this->persist($storage, true);

        return $this->redirectToRoute('opensoft_storage_list_storages');
    }

    /**
     * @Route("/admin/storage/{id}/delete", name="opensoft_storage_delete_storage", requirements={"id" = "\d+"})
     *
     * @param Storage $storage
     * @return RedirectResponse
     */
    public function deleteAction(Storage $storage): RedirectResponse
    {
        if (!$this->isGranted('ROLE_ADMIN_STORAGE_MANAGER')) {
            throw new AccessDeniedException();
        }

        $doctrine = $this->getDoctrine();
        $stats = $doctrine->getRepository(StorageFile::class)->statsByStorage($storage);
        if ($stats['file_count'] > 0) {
            $this->addFlash('error', 'This storage still has files associated with it.  Please migrate them to another storage location before deleting this one.');

            return $this->redirectToRoute('opensoft_storage_list_storages');
        }

        $em = $doctrine->getManager();

        $em->remove($storage);
        $em->flush();

        return $this->redirectToRoute('opensoft_storage_list_storages');
    }

    /**
     * @Route("/admin/storage/policies", name="opensoft_storage_list_storage_policies")
     *
     * @return Response
     */
    public function listPoliciesAction(): Response
    {
        $policies = $this->getDoctrine()->getRepository(StoragePolicy::class)->findAllIndexedByType();
        $types = $this->storageFileTypeProvider->getTypes();

        return $this->render('@OpensoftStorage/storage/list_policies.html.twig', [
            'types' => $types,
            'policies' => $policies
        ]);
    }

    /**
     * @Route("/admin/storage/policies/{type}/create", name="opensoft_storage_create_storage_policy", requirements={"type" = "\d+"})
     *
     * @param Request $request
     * @param int $type
     * @return Response|RedirectResponse
     */
    public function createPolicyAction(Request $request, int $type): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_STORAGE_MANAGER')) {
            throw new AccessDeniedException();
        }

        $availableTypes = $this->storageFileTypeProvider->getTypes();
        if (!isset($availableTypes[$type])) {
            throw $this->createNotFoundException(sprintf("Type '%d' is not a valid type", $type));
        }

        $storagePolicy = new StoragePolicy();
        $storagePolicy->setType($type);
        $form = $this->createForm(StoragePolicyFormType::class, $storagePolicy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->persist($storagePolicy, true);

            $this->addFlash('success', sprintf('Storage File Policy created for %s', $availableTypes[$type]));

            return $this->redirectToRoute('opensoft_storage_list_storage_policies');
        }

        return $this->render('@OpensoftStorage/storage/create_policy.html.twig', [
            'types' => $availableTypes,
            'policy' => $storagePolicy,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/admin/storage/policies/{id}/edit", name="opensoft_storage_edit_storage_policy", requirements={"id" = "\d+"})
     *
     * @param Request $request
     * @param StoragePolicy $storagePolicy
     * @return Response|RedirectResponse
     */
    public function editPolicyAction(Request $request, StoragePolicy $storagePolicy): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_STORAGE_MANAGER')) {
            throw new AccessDeniedException();
        }

        $availableTypes = $this->storageFileTypeProvider->getTypes();
        $form = $this->createForm(StoragePolicyFormType::class, $storagePolicy);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->persist($storagePolicy, true);

            $this->addFlash('success', sprintf('Storage File Policy created for %s', $availableTypes[$storagePolicy->getType()]));

            return $this->redirectToRoute('opensoft_storage_list_storage_policies');
        }

        return $this->render('@OpensoftStorage/storage/edit_policy.html.twig', [
            'types' =>$availableTypes,
            'policy' => $storagePolicy,
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/admin/storage/policies/{id}/delete", name="opensoft_storage_delete_storage_policy", requirements={"id" = "\d+"})
     *
     * @param StoragePolicy $storagePolicy
     * @return RedirectResponse
     */
    public function deletePolicyAction(StoragePolicy $storagePolicy): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_STORAGE_MANAGER')) {
            throw new AccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();
        $em->remove($storagePolicy);
        $em->flush();

        $this->addFlash('success', 'Storage policy deleted.');

        return $this->redirectToRoute('opensoft_storage_list_storage_policies');
    }

    /**
     * @Route("/admin/storage-file/{id}", name="opensoft_storage_show_storage_file", requirements={"id" = "\d+"})
     *
     * @param StorageFile $file
     * @return Response
     */
    public function viewFileAction(StorageFile $file): Response
    {
        return $this->render('@OpensoftStorage/storage/show_file.html.twig', [
            'file' => $file,
            'fileTypes' => $this->storageFileTypeProvider->getTypes(),
            'policy' => $this->getDoctrine()->getRepository(StoragePolicy::class)->findOneByType($file->getType())
        ]);
    }

    /**
     * @Route("/admin/storage-file/{id}/delete", name="opensoft_storage_delete_storage_file", requirements={"id" = "\d+"})
     *
     * @param StorageFile $file
     * @return Response
     */
    public function deleteFileAction(StorageFile $file): Response
    {
        if (!$this->isGranted('ROLE_ADMIN_STORAGE_MANAGER')) {
            throw new AccessDeniedException();
        }

        $fileId = $file->getId();
        $em = $this->getDoctrine()->getManager();
        try {
            // some files cannot be removed safely if they do not have db level delete cascade behavior defined
            $em->remove($file);
            $em->flush();
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('opensoft_storage_show_storage_file', ['id' => $fileId]);
        }

        $this->addFlash('success', sprintf("Storage file '%d' successfully deleted", $fileId));

        return $this->redirectToRoute('opensoft_storage_show_storage', ['id' => $file->getStorage()->getId()]);
    }

    /**
     * @param Request $request
     * @param Storage $storage
     * @return RedirectResponse|Response
     */
    private function handleFormRequest(Request $request, Storage $storage): Response
    {
        $form = $this->createForm(StorageType::class, $storage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            // suspend auto-commit to ensure the fs is writeable before commiting
            $em->getConnection()->beginTransaction();

            try {
                // persist and flush to ensure slug gets created properly for gaufrette stream mapper
                $em->persist($storage);
                $em->flush();

                // ensure we can construct the file system proxy properly
                $fs = $this->storageManager->filesystem($storage);
                $testFilename = uniqid('test_tmp_') . '.bak';
                $fs->write($testFilename, 'content');
                $fs->delete($testFilename);

                // everything happy, commit transaction
                $em->getConnection()->commit();
            } catch (\Exception $e) {
                $this->addFlash('error', sprintf(
                    'Could not create/validate storage: <br /><br /><strong>%s</strong>: %s',
                    get_class($e),
                    $e->getMessage()
                ));

                // roll back the transaction
                $em->getConnection()->rollBack();

                return $this->render('@OpensoftStorage/storage/edit.html.twig', [
                    'form' => $form->createView(),
                    'storage' => $storage,
                ]);
            }

            return $this->redirectToRoute('opensoft_storage_list_storages');
        }

        return $this->render('@OpensoftStorage/storage/edit.html.twig', [
            'form' => $form->createView(),
            'storage' => $storage,
        ]);
    }


    /**
     * Persist an object with Doctrine's Entity Manager
     *
     * @param object $entity
     * @param bool   $flush
     */
    private function persist($entity, bool $flush = false): void
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);

        if ($flush) {
            $em->flush($entity);
        }
    }
}
