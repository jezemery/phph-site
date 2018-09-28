<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EventController extends Controller
{
    public function index(): Response
    {
        $events = null;
        try {
            /** @var Event $events */
            $events = $this->getDoctrine()->getRepository('App:Event')->findAll();
        } catch (\Exception $e) {
            $this->generateStorageFault();
        }

        return $this->render('admin/event_index.html.twig', [
            'title' => 'Events',
            'events' => $events,
        ]);
    }

    /**
     * @Route("/admin/event/add", name="event_add")
     * @Template("admin/event_add.html.twig")
     * @Method("GET")
     * @return array
     */
    public function add(): array
    {
        return [
            'title' => 'Add an event',
            'form' => $this->createForm(EventType::class)->createView(),
        ];
    }

    /**
     * @Route("/admin/event/{id}/edit", name="event_edit")
     * @Template("admin/event_add.html.twig")
     * @Method("GET")
     * @param Event $id
     *
     * @return array
     */
    public function edit(Event $id): array
    {
        return [
            'title' => 'Edit an event',
            'form' => $this->createForm(EventType::class, $id)->createView(),
        ];
    }

    /**
     * @param Request $request
     * @param Event $id
     *
     * @return RedirectResponse
     */
    public function editPost(Request $request, Event $id): RedirectResponse
    {
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(EventType::class, $id);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $session = $this->get('session');
            $flash = $session->getFlashBag();
            $flash->add('success', 'Your changes have been saved');
        }

        return $this->redirectToRoute('event_management');
    }

    /**
     * @Route("/admin/event/add", name="event_add_post")
     * @Method("POST")
     * @param Request $request
     *
     * @return array
     */
    public function addPost(Request $request): RedirectResponse
    {
        $data = $request->request->all()['event'];
        $om = $this->getDoctrine()->getManager();
        $event = new Event();
        $location = $om->getRepository('App:Location')->find($data['location']);
        $event->setLocation($location);
        $event->setDate(new \DateTime($data['date']));

        try {
            $om->persist($event);
            $om->flush();
            $session = $this->get('session');
            $flash = $session->getFlashBag();
            $flash->add('success', 'Your changes have been saved');
        } catch (\Exception $e) {
            $session = $this->get('session');
            $flash = $session->getFlashBag();
            $flash->add('error', 'There was a problem saving your changes');
            $this->get('logger')->addError($e->getMessage());
        }

        return $this->redirectToRoute('event_management');
    }

    /**
     * @Route("/admin/event/{id}/members", name="event_member_management")
     * @Template("admin/member_index.html.twig")
     * @Method("GET")
     * @param Event $id
     *
     * @return array
     */
    public function eventMemberIndex(Event $id): array
    {
        $events = null;
        try {
            $members = $this->getDoctrine()->getRepository('App:Member')->findBy(['event' => $id]);
        } catch (\Exception $e) {
            $this->generateStorageFault();
        }

        return [
            'title' => 'Members attending in ' . $id->getDate()->format('M Y'),
            'members' => $members,
        ];
    }


    private function generateStorageFault(): void
    {
        $session = $this->get('session');
        $flash = $session->getFlashBag();
        $flash->add('error', 'Sorry we are having problems connection to the storage right now');
    }

}