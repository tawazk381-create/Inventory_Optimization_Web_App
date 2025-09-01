<?php
// File: app/controllers/WarehouseController.php
declare(strict_types=1);

class WarehouseController extends Controller
{
    protected $auth;
    protected $warehouseModel;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->warehouseModel = new Warehouse();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->auth->check()) {
            if (php_sapi_name() !== 'cli') {
                header('Location: /login');
                exit;
            }
        }
    }

    /**
     * List warehouses (with pagination)
     */
    public function index(): void
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(10, (int)($_GET['per_page'] ?? 15));
        $offset  = ($page - 1) * $perPage;

        $total      = $this->warehouseModel->countAll();
        $warehouses = $this->warehouseModel->paginate($perPage, $offset);

        $this->view('warehouses/index', [
            'title'      => 'Warehouses',
            'warehouses' => $warehouses,
            'user'       => $this->auth->user(),
            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $total
        ]);
    }

    /**
     * Show form to create a new warehouse
     */
    public function create(): void
    {
        $this->view('warehouses/create', [
            'title' => 'Add Warehouse',
            'user'  => $this->auth->user(),
        ]);
    }

    /**
     * Store a new warehouse
     */
    public function store(): void
    {
        $data = [
            'name'     => trim($_POST['name'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'contact'  => trim($_POST['contact'] ?? ''),
        ];

        if ($data['name'] === '') {
            $_SESSION['flash_error'] = "Warehouse name is required.";
            $this->redirect('/warehouses/create');
        }

        $this->warehouseModel->create($data);

        $_SESSION['flash_success'] = "Warehouse created successfully.";
        $this->redirect('/warehouses');
    }

    /**
     * Show a single warehouse
     */
    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = "Invalid warehouse ID.";
            $this->redirect('/warehouses');
        }

        $warehouse = $this->warehouseModel->findById($id);
        if (!$warehouse) {
            $_SESSION['flash_error'] = "Warehouse not found.";
            $this->redirect('/warehouses');
        }

        $this->view('warehouses/show', [
            'title'     => 'View Warehouse',
            'warehouse' => $warehouse,
            'user'      => $this->auth->user(),
        ]);
    }

    /**
     * Show edit form
     */
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = "Invalid warehouse ID.";
            $this->redirect('/warehouses');
        }

        $warehouse = $this->warehouseModel->findById($id);
        if (!$warehouse) {
            $_SESSION['flash_error'] = "Warehouse not found.";
            $this->redirect('/warehouses');
        }

        $this->view('warehouses/edit', [
            'title'     => 'Edit Warehouse',
            'warehouse' => $warehouse,
            'user'      => $this->auth->user(),
        ]);
    }

    /**
     * Update warehouse
     */
    public function update(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = "Invalid warehouse ID.";
            $this->redirect('/warehouses');
        }

        $data = [
            'name'     => trim($_POST['name'] ?? ''),
            'location' => trim($_POST['location'] ?? ''),
            'contact'  => trim($_POST['contact'] ?? ''),
        ];

        if ($data['name'] === '') {
            $_SESSION['flash_error'] = "Warehouse name is required.";
            $this->redirect('/warehouses/edit?id=' . $id);
        }

        $this->warehouseModel->update($id, $data);

        $_SESSION['flash_success'] = "Warehouse updated successfully.";
        $this->redirect('/warehouses/show?id=' . $id);
    }

    /**
     * Delete warehouse
     */
    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->warehouseModel->delete($id);
            $_SESSION['flash_success'] = "Warehouse deleted.";
        } else {
            $_SESSION['flash_error'] = "Invalid warehouse ID.";
        }

        $this->redirect('/warehouses');
    }
}
