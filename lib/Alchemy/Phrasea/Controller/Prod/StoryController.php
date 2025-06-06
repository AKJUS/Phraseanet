<?php
/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2016 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Alchemy\Phrasea\Controller\Prod;

use Alchemy\Phrasea\Application\Helper\DispatcherAware;
use Alchemy\Phrasea\Application\Helper\EntityManagerAware;
use Alchemy\Phrasea\Controller\Controller;
use Alchemy\Phrasea\Controller\Exception as ControllerException;
use Alchemy\Phrasea\Controller\RecordsRequest;
use Alchemy\Phrasea\Core\Event\RecordEdit;
use Alchemy\Phrasea\Core\PhraseaEvents;
use Alchemy\Phrasea\Model\Entities\StoryWZ;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StoryController extends Controller
{
    use DispatcherAware;
    use EntityManagerAware;

    public function displayCreateFormAction(Request $request)
    {
        $records = RecordsRequest::fromRequest($this->app, $request, true);

        $databoxes = $records->databoxes();
        $collections = $records->collections();

        $storyTitleMetaStructIds = [];
        foreach ($this->getApplicationBox()->get_databoxes() as $db) {
            foreach ($db->get_meta_structure() as $metaStruct) {
                if ($metaStruct->get_thumbtitle() != "0" &&
                    !$metaStruct->is_readonly() &&
                    $metaStruct->get_gui_editable() &&
                    $metaStruct->get_type() == \databox_field::TYPE_STRING
                ) {
                    $storyTitleMetaStructIds[$db->get_sbas_id()][] = ['meta_struct_id' => $metaStruct->get_id(), 'label' => $metaStruct->get_label($this->app['locale'])];
                }
            }
        }

        $this->setSessionFormToken('prodCreateStory');

        return $this->render('prod/Story/Create.html.twig', [
            'isMultipleDataboxes'   => count($databoxes) > 1 ? 1 : 0,
            'isMultipleCollections' => count($collections) > 1 ? 1 : 0,
            'databoxId'             => count($databoxes) == 1 ? current($databoxes)->get_sbas_id() : 0,
            'collectionId'          => count($collections) == 1 ? current($collections)->get_base_id() : 0,
            'storyTitleMetaStructIds'   => $storyTitleMetaStructIds
        ]);
    }

    public function postCreateFormAction(Request $request)
    {
        if (!$this->isCrsfValid($request, 'prodCreateStory')) {
            return $this->app->json(['success' => false , 'message' => 'invalid form'], 403);
        }

        $collection = \collection::getByBaseId($this->app, $request->request->get('base_id'));

        if (!$this->getAclForUser()->has_right_on_base($collection->get_base_id(), \ACL::CANADDRECORD)) {
            throw new AccessDeniedHttpException('You can not create a story on this collection');
        }

        $story = \record_adapter::createStory($this->app, $collection);
        $records = RecordsRequest::fromRequest($this->app, $request, true);

        foreach ($records as $record) {
            if ($story->hasChild($record)) {
                continue;
            }

            $story->appendChild($record);
        }

        $metadatas = [];

        $titleFields = $request->request->get('name');

        foreach ($titleFields as $db_metastructId => $value) {
            $t = explode('-', $db_metastructId);
            if ($t[0] == $collection->get_databox()->get_sbas_id()) {
                $metadatas[] = [
                    'meta_struct_id' => $t[1],
                    'meta_id'        => null,
                    'value'          => $value,
                ];
            }
        }

        $recordAdapter = $story->set_metadatas($metadatas);

        $storyWZ = new StoryWZ();
        $storyWZ->setUser($this->getAuthenticatedUser());
        $storyWZ->setRecord($story);

        $manager = $this->getEntityManager();
        $manager->persist($storyWZ);
        $manager->flush();

        if ($request->getRequestFormat() == 'json') {
            $data = [
                'success'  => true,
                'message'  => $this->app->trans('Story created'),
                'WorkZone' => $storyWZ->getId(),
                'story'    => [
                    'sbas_id'   => $story->getDataboxId(),
                    'record_id' => $story->getRecordId(),
                ],
            ];

            return $this->app->json($data);
        }

        return $this->app->redirectPath('prod_stories_story', [
            'sbas_id' => $storyWZ->getSbasId(),
            'record_id' => $storyWZ->getRecordId(),
        ]);
    }

    public function showAction(Request $request, $sbas_id, $record_id)
    {
        $ouputFormat = $request->getRequestFormat();
        $story = new \record_adapter($this->app, $sbas_id, $record_id);

        $ret = [
            'html' => $this->render('prod/WorkZone/Story.html.twig', ['Story' => $story]),
            'data' => [
                'classes' => [],
                'removeClasses' => []
            ]
        ];

        if($ouputFormat === "json") {
            // return advanced format containig share, feedback... infos and html
            return $this->app->json($ret);
        }
        // default return html
        return $ret['html'];
    }

    public function addElementsAction(Request $request, $sbas_id, $record_id)
    {
        $Story = new \record_adapter($this->app, $sbas_id, $record_id);
        $previousDescription = $Story->getRecordDescriptionAsArray();

        if (!$this->getAclForUser()->has_right_on_base($Story->getBaseId(), \ACL::CANMODIFRECORD)) {
            throw new AccessDeniedHttpException('You can not add document to this Story');
        }

        $n = 0;

        $records = RecordsRequest::fromRequest($this->app, $request, true);

        foreach ($records as $record) {
            if ($Story->hasChild($record)) {
                continue;
            }

            $Story->appendChild($record);
            $n++;
        }

        $this->dispatch(PhraseaEvents::RECORD_EDIT, new RecordEdit($Story, $previousDescription));

        $data = [
            'success' => true,
            'message' => $this->app->trans('%quantity% records added', ['%quantity%' => $n]),
        ];

        if ($request->getRequestFormat() == 'json') {
            return $this->app->json($data);
        }

        return $this->app->redirectPath('prod_stories_story', ['sbas_id' => $sbas_id, 'record_id' => $record_id]);
    }

    public function removeElementAction(Request $request, $sbas_id, $record_id, $child_sbas_id, $child_record_id)
    {
        $story = new \record_adapter($this->app, $sbas_id, $record_id);
        $record = new \record_adapter($this->app, $child_sbas_id, $child_record_id);

        if (!$this->getAclForUser()->has_right_on_base($story->getBaseId(), \ACL::CANMODIFRECORD)) {
            throw new AccessDeniedHttpException('You can not add document to this Story');
        }

        $story->removeChild($record);

        $data = [
            'success' => true,
            'message' => $this->app->trans('Record removed from story'),
        ];


        $this->dispatch(PhraseaEvents::RECORD_EDIT, new RecordEdit($story));

        if ($request->getRequestFormat() == 'json') {
            return $this->app->json($data);
        }

        return $this->app->redirectPath('prod_stories_story', ['sbas_id' => $sbas_id, 'record_id' => $record_id]);
    }

    public function displayReorderFormAction($sbas_id, $record_id)
    {
        $story = new \record_adapter($this->app, $sbas_id, $record_id);

        if (!$story->isStory()) {
            throw new \Exception('This is not a story');
        }

        $this->setSessionFormToken('prodStoryReorder');

        return $this->renderResponse('prod/Story/Reorder.html.twig', [
            'story' => $story,
        ]);
    }

    public function reorderAction(Request $request, $sbas_id, $record_id)
    {
        if (!$this->isCrsfValid($request, 'prodStoryReorder')) {
            return $this->app->json(['success' => false , 'message' => 'invalid form'], 403);
        }

        try {
            $story = new \record_adapter($this->app, $sbas_id, $record_id);
            $previousDescription = $story->getRecordDescriptionAsArray();

            if (!$story->isStory()) {
                throw new \Exception('This is not a story');
            }

            if (!$this->getAclForUser()->has_right_on_base($story->getBaseId(), \ACL::CANMODIFRECORD)) {
                throw new ControllerException($this->app->trans('You can not edit this story'));
            }

            $sql = 'UPDATE regroup SET ord = :ord WHERE rid_parent = :parent_id AND rid_child = :children_id';
            $stmt = $story->getDatabox()->get_connection()->prepare($sql);

            foreach ($request->request->get('element') as $record_id => $ord) {
                $params = [
                    ':ord'         => $ord,
                    ':parent_id'   => $story->getRecordId(),
                    ':children_id' => $record_id
                ];
                $stmt->execute($params);
            }

            $stmt->closeCursor();

            $this->dispatch(PhraseaEvents::RECORD_EDIT, new RecordEdit($story, $previousDescription));
            $ret = ['success' => true, 'message' => $this->app->trans('Story updated')];
        } catch (ControllerException $e) {
            $ret = ['success' => false, 'message' => $e->getMessage()];
        } catch (\Exception $e) {
            $ret = ['success' => false, 'message' => $this->app->trans('An error occured')];
        }

        return $this->app->json($ret);
    }
}
