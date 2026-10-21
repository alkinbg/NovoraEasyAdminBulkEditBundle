<?php

declare(strict_types=1);

namespace Novora\EasyAdminBulkEditBundle\Tests\Fixtures;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class IntegrationDashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return new Response('Admin test dashboard');
    }
}
