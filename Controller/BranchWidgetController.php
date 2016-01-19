<?php
/*
 * Copyright (c) 2014 Eltrino LLC (http://eltrino.com)
 *
 * Licensed under the Open Software License (OSL 3.0).
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@eltrino.com so we can send you a copy immediately.
 */
namespace Diamante\DeskBundle\Controller;

use Diamante\DeskBundle\Entity\Ticket;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use Diamante\DeskBundle\Model\Branch\Exception\DefaultBranchException;

/**
 * @Route("branches")
 */
class BranchWidgetController extends WidgetController
{
    /**
     * @Route(
     *      "/deleteBranchViewForm/{id}",
     *      name="diamante_branch_view_delete_form",
     *      requirements={"id"="\d+"}
     * )
     * @Template("DiamanteDeskBundle:Branch/widgets:deleteForm.html.twig")
     *
     */
    public function deleteBranchViewForm($id)
    {
        $response = $this->deleteBranchForm($id);
        $response['delete_route'] = 'diamante_branch_view_delete_form';

        return $response;
    }

    /**
     * @Route(
     *      "/deleteBranchListForm/{id}",
     *      name="diamante_branch_list_delete_form",
     *      requirements={"id"="\d+"}
     * )
     * @Template("DiamanteDeskBundle:Branch/widgets:deleteForm.html.twig")
     *
     */
    public function deleteBranchListForm($id)
    {
        $response = $this->deleteBranchForm($id, false);
        $response['delete_route'] = 'diamante_branch_list_delete_form';

        return $response;
    }

    /**
     * @Route(
     *      "/{gridName}/massAction/{actionName}",
     *      name="diamante_branch_mass_action",
     *      requirements={"gridName"="[\w\:-]+", "actionName"="[\w-]+"},
     *      options= {"expose"= true}
     * )
     *
     * @Template("DiamanteDeskBundle:Branch/widgets:deleteMassForm.html.twig")
     *
     * @param string $gridName
     * @param string $actionName
     *
     * @return Response
     * @throws \LogicException
     */
    public function massActionAction($gridName, $actionName)
    {
        $request = $this->getRequest();

        try {
            $form = $this->createForm('diamante_mass_delete_branch_form', ['values' => $request->get('values')]);

            if (true === $this->widgetRedirectRequested()) {
                $response = ['form' => $form->createView()];

                return $response;
            }

            $this->handle($form);
            $data = $form->getData();

            $newBranchId = $data['newBranch'];
            $removeBranches = explode(',', $data['removeBranches']);
            $branchService = $this->get('diamante.branch.service');

            if ($data['moveMassTickets']) {
                foreach ($removeBranches as $branchId) {
                    $tickets = $this->getAllTickets($branchId);

                    foreach ($tickets as $ticket) {
                        $this->moveTicket($ticket, $newBranchId);
                    }

                    $branchService->deleteBranch($branchId);
                }
            }

            $this->addSuccessMessage('diamante.desk.branch.messages.delete.success');
            $response = $this->getWidgetResponse();

        } catch (\Exception $e) {
            $this->handleException($e);
            $response = ['form' => $form->createView()];
        }

        return $response;
    }

    /**
     * @param integer $id
     * @param bool    $redirect
     *
     * @return array
     */
    private function deleteBranchForm($id, $redirect = true)
    {
        try {
            $form = $this->createForm('diamante_delete_branch_form', ['id' => $id]);

            if (true === $this->widgetRedirectRequested()) {
                $response = ['form' => $form->createView()];
                return $response;
            }

            $systemSettings = $this->get('diamante.email_processing.mail_system_settings');
            if ($systemSettings->getDefaultBranchId() == $id) {
                throw new DefaultBranchException();
            }

            $this->handle($form);
            $data = $form->getData();

            $tickets = [];
            $newBranchId = $data['newBranch'];
            $branchService = $this->get('diamante.branch.service');

            if ($data['moveTickets']) {
                $tickets = $this->getAllTickets($newBranchId);
            }

            foreach ($tickets as $ticket) {
                $this->moveTicket($ticket, $newBranchId);
            }

            $branchService->deleteBranch($id);
            $this->addSuccessMessage('diamante.desk.branch.messages.delete.success');
            $response = $this->getWidgetResponse();

            if ($redirect) {
                $response['redirect'] = $this->generateUrl('diamante_branch_list');
            }

        } catch (DefaultBranchException $e) {
            $this->handleException($e);
            $response = $this->getWidgetResponse();
        } catch (\Exception $e) {
            $this->handleException($e);
            $response = ['form' => $form->createView()];
        }

        return $response;
    }

    /**
     * @param $branchId
     *
     * @return array|\Diamante\DeskBundle\Entity\Ticket[]
     */
    private function getAllTickets($branchId)
    {
        return $this->getDoctrine()
            ->getRepository('DiamanteDeskBundle:Ticket')
            ->findBy(['branch' => $branchId]);
    }

    /**
     * @param Ticket  $ticket
     * @param integer $newBranchId
     *
     * @return $this
     */
    private function moveTicket(Ticket $ticket, $newBranchId)
    {
        $branchService = $this->get('diamante.branch.service');
        $command = $this->get('diamante.command_factory')
            ->createMoveTicketCommand($ticket);

        $command->branch = $branchService->getBranch($newBranchId);

        if ($command->branch->getId() != $ticket->getBranch()->getId()) {
            $this->get('diamante.ticket.service')->moveTicket($command);
        }

        return $this;
    }
}
