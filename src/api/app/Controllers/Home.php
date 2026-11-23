<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * La raíz del dominio lleva a la SPA servida en `/app/`.
 */
final class Home extends BaseController
{
    public function index(): RedirectResponse
    {
        return redirect()->to(base_url('app/'));
    }
}
