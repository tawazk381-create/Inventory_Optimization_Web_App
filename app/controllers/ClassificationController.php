<?php
// File: app/controllers/ClassificationController.php
declare(strict_types=1);

class ClassificationController extends Controller
{
    protected $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->requireRole(['Admin','Manager']);
    }

    public function index()
    {
        // Fetch items
        $itemsStmt = $this->db->query("
            SELECT id, sku, name, COALESCE(unit_price,0) AS unit_price
            FROM items
        ");
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Compute annual value (placeholder: daily demand is 0)
        $rows = [];
        foreach ($items as $it) {
            $avgDaily = 0.0; // TODO: replace with real demand metric
            $annualDemand = $avgDaily * 365.0;
            $annualValue  = $annualDemand * ((float)$it['unit_price']);
            $rows[] = [
                'id'             => (int)$it['id'],
                'sku'            => $it['sku'],
                'name'           => $it['name'],
                'annual_demand'  => $annualDemand,
                'annual_value'   => $annualValue,
                'abc'            => '-',   // placeholder, updated below
                'xyz'            => '-',   // placeholder until demand variation is available
                'cv'             => null,  // coefficient of variation (placeholder)
            ];
        }

        // --- ABC classification (based on annual value) ---
        usort($rows, fn($a,$b) => $b['annual_value'] <=> $a['annual_value']);
        $totalValue = array_sum(array_column($rows, 'annual_value'));
        $cum = 0.0;
        foreach ($rows as &$r) {
            $share = $totalValue > 0 ? ($r['annual_value'] / $totalValue) : 0;
            $cum += $share;
            $r['abc'] = $cum <= 0.70 ? 'A' : ($cum <= 0.90 ? 'B' : 'C');
        }
        unset($r);

        // --- XYZ classification (placeholder) ---
        // If you later add demand history, compute CV = stddev / mean demand here.
        foreach ($rows as &$r) {
            $r['xyz'] = '-';
            $r['cv']  = null;
        }
        unset($r);

        $this->view('classification/index.php', [
            'title' => 'ABC / XYZ Classification',
            'rows'  => $rows
        ]);
    }
}
