<?php
// File: app/controllers/SupplierController.php
declare(strict_types=1);

class SupplierController extends Controller
{
    protected $auth;
    protected $supplierModel;
    protected $supplierService;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->supplierModel = new Supplier();
        $this->supplierService = new SupplierService();

        if (!$this->auth->check()) {
            if (php_sapi_name() !== 'cli') {
                $this->redirect('/login');
                exit;
            }
        }
    }

    /**
     * List suppliers (with pagination)
     */
    public function index()
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(10, (int)($_GET['per_page'] ?? 15));
        $offset  = ($page - 1) * $perPage;

        $total     = $this->supplierModel->countAll();
        $suppliers = $this->supplierModel->paginate($perPage, $offset);

        $this->view('suppliers/index', [
            'title'     => 'Suppliers',
            'suppliers' => $suppliers,
            'user'      => $this->auth->user(),
            'page'      => $page,
            'perPage'   => $perPage,
            'total'     => $total
        ]);
    }

    /**
     * Show form to create a new supplier
     */
    public function create()
    {
        $this->view('suppliers/create', [
            'title' => 'Add Supplier',
            'user'  => $this->auth->user(),
        ]);
    }

    /**
     * Store new supplier
     */
    public function store()
    {
        $data = [
            'name'          => trim($_POST['name'] ?? ''),
            'contact_name'  => trim($_POST['contact_name'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'phone'         => trim($_POST['phone'] ?? ''),
            'address'       => trim($_POST['address'] ?? ''),
        ];

        $this->supplierModel->create($data);

        $_SESSION['flash_success'] = "Supplier created successfully.";
        $this->redirect('/suppliers');
    }

    /**
     * Show a single supplier
     */
    public function show()
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = "Invalid supplier ID.";
            $this->redirect('/suppliers');
        }

        $supplier = $this->supplierModel->find($id);
        if (!$supplier) {
            $_SESSION['flash_error'] = "Supplier not found.";
            $this->redirect('/suppliers');
        }

        // Example KPIs — from SupplierService
        $kpi = $this->supplierService->getSupplierKPIs($id);

        $this->view('suppliers/show', [
            'title'    => 'View Supplier',
            'supplier' => $supplier,
            'kpi'      => $kpi,
            'user'     => $this->auth->user(),
        ]);
    }

    /**
     * Show form to edit a supplier
     */
    public function edit()
    {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = "Invalid supplier ID.";
            $this->redirect('/suppliers');
        }

        $supplier = $this->supplierModel->find($id);
        if (!$supplier) {
            $_SESSION['flash_error'] = "Supplier not found.";
            $this->redirect('/suppliers');
        }

        $this->view('suppliers/edit', [
            'title'    => 'Edit Supplier',
            'supplier' => $supplier,
            'user'     => $this->auth->user(),
        ]);
    }

    /**
     * Update supplier
     */
    public function update()
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = "Invalid supplier ID.";
            $this->redirect('/suppliers');
        }

        $data = [
            'name'          => trim($_POST['name'] ?? ''),
            'contact_name'  => trim($_POST['contact_name'] ?? ''),
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'phone'         => trim($_POST['phone'] ?? ''),
            'address'       => trim($_POST['address'] ?? ''),
        ];

        $this->supplierModel->update($id, $data);

        $_SESSION['flash_success'] = "Supplier updated successfully.";
        $this->redirect('/suppliers/show?id=' . $id);
    }

    /**
     * Delete supplier
     */
    public function delete()
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->supplierModel->delete($id);
            $_SESSION['flash_success'] = "Supplier deleted.";
        } else {
            $_SESSION['flash_error'] = "Invalid supplier ID.";
        }

        $this->redirect('/suppliers');
    }
}
