<?php

namespace App\Domain\AiInsights\Services;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\BusinessHealthService;
use App\Domain\Finance\Models\DepreciationSchedule;
use App\Domain\Finance\Services\BudgetService;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Finance\Services\FinanceDashboardService;
use App\Domain\Finance\Services\FinancialStatementService;
use App\Domain\Finance\Services\GeneralLedgerService;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Payroll\Models\PayrollPeriod;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Services\SalesDashboardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Routes business questions to real DB queries first, then falls back to
 * OpenAI with a rich snapshot as context. Detected language is stored
 * per-call so every response is delivered in the same language the user
 * wrote in (English or Swahili).
 */
class BusinessAssistantService
{
    /** Detected per ask() call — 'en' or 'sw'. */
    private string $lang = 'en';

    public function __construct(
        private readonly SalesDashboardService $salesDashboard,
        private readonly BusinessHealthService $businessHealth,
        private readonly AiNarrativeService $narrative,
        private readonly FinanceDashboardService $financeDashboard,
        private readonly FinancialStatementService $financialStatements,
        private readonly GeneralLedgerService $generalLedger,
        private readonly ChartOfAccountsService $chartOfAccounts,
        private readonly BudgetService $budgetService,
    ) {}

    /**
     * @return array{answer: string, source: string}
     */
    public function ask(Business $business, string $question): array
    {
        $this->lang = $this->detectLanguage($question);

        $q   = Str::lower($question);
        $has  = fn (array $words): bool => Str::contains($q, $words);
        // Word-boundary match — prevents "hi" from firing on "which", "this", "vehicle", etc.
        $word = fn (string $w): bool => (bool) preg_match('/\b' . preg_quote($w, '/') . '\b/i', $q);

        return match (true) {

            // ── GREETINGS ─────────────────────────────────────────────────────
            // Short words (hi, hey) use whole-word regex; longer words use substring.
            $word('hi') || $word('hey') || $has(['hello', 'good morning', 'good afternoon',
                  'good evening', 'how are you', 'greetings',
                  'hujambo', 'habari', 'mambo', 'salamu', 'shikamoo',
                  'karibu', 'niambie', 'nisaidie', 'nikusaidie']) => $this->greetingAnswer($business),

            // ── YESTERDAY (must come before generic "today/faida/profit") ──────
            $has(['yesterday', "yesterday's",
                  'jana', 'jana nilifanya', 'jana mauzo', 'jana faida',
                  'jana mapato', 'jana nilipata', 'nimepata jana',
                  'kiasi gani jana', 'faida gani jana']) => $this->yesterdayAnswer($business),

            // ── THIS WEEK ────────────────────────────────────────────────────
            $has(['this week', 'week sales', 'weekly sales', 'week revenue',
                  'wiki hii', 'mauzo ya wiki', 'wiki hii mauzo', 'mapato ya wiki',
                  'wiki iliyopita', 'last week', 'past week']) => $this->thisWeekAnswer($business),

            // ── INVENTORY ────────────────────────────────────────────────────

            $has(['reorder', 'restock', 'low stock', 'running out', 'out of stock',
                  'need to order', 'need to buy',
                  'agiza tena', 'hisa ndogo', 'zinaisha', 'ongeza hisa', 'hifadhi ndogo',
                  'bidhaa zinaisha', 'zimekwisha', 'haitoshi', 'pungufu ya hisa',
                  'karibu kuisha', 'inaisha', 'zinakwisha', 'ninahitaji kuagiza',
                  'niagize nini', 'niagize']) => $this->reorderAnswer($business),

            $has(['stock value', 'inventory value', 'inventory worth', 'value of stock',
                  'worth of stock', 'current stock value', 'how much is my stock',
                  'value of my inventory', 'total stock worth',
                  'thamani ya bidhaa', 'thamani ya hisa', 'thamani ya stok',
                  'bidhaa zina thamani', 'bei ya hisa', 'stok ina thamani',
                  'thamani ya akiba', 'hisa ina thamani', 'hisa zangu ina thamani',
                  'stok yangu ina thamani']) => $this->stockValueAnswer($business),

            $has(['expir', 'near expiry', 'shelf life', 'about to expire',
                  'muda wa kuisha', 'zimeisha muda', 'kuharibika', 'zinaisha muda',
                  'muda umepita', 'zitaisha', 'bidhaa mbaya',
                  'zimepita muda', 'zinaoza', 'bidhaa za muda mfupi',
                  'bidhaa zinaharibika', 'zinaharibika']) => $this->expiryAnswer($business),

            $has(['slow moving', 'slow-moving', 'dead stock', 'not selling', 'no movement',
                  'not moving', 'unsold stock',
                  'haziuzi', 'bidhaa za polepole', 'stok iliyosimama',
                  'hazinunuliwa', 'hazitoki', 'hazisogei', 'zinabaki tu',
                  'haziendi', 'zinatulia', 'bidhaa haziendi']) => $this->slowMoversAnswer($business),

            $has(['best sell', 'top product', 'most popular', 'most sold', 'top sell',
                  'best product', 'highest revenue product', 'highest selling',
                  'which product', 'what product',
                  'zinauzwa zaidi', 'bidhaa bora', 'inayouza zaidi', 'bidhaa maarufu',
                  'nini kinauzwa zaidi', 'kinachopendwa zaidi', 'kinachonunuliwa zaidi',
                  'bidhaa zinazouza', 'bidhaa za juu', 'bidhaa gani inauzwa',
                  'bidhaa gani zinauzwa', 'gani inauzwa zaidi']) => $this->topProductsAnswer($business),

            $has(['how many product', 'total product', 'number of product', 'product count',
                  'how many items', 'catalog size', 'how many sku',
                  'bidhaa ngapi', 'idadi ya bidhaa', 'jumla ya bidhaa',
                  'una bidhaa ngapi', 'orodha ina bidhaa ngapi',
                  'nina bidhaa ngapi', 'bidhaa zangu ni ngapi']) => $this->productCountAnswer($business),

            // ── CUSTOMERS ────────────────────────────────────────────────────

            $has(['top customer', 'best customer', 'biggest customer', 'most loyal',
                  'who buys most', 'highest spending customer',
                  'wateja bora', 'mteja mkubwa', 'wateja wakubwa', 'anayenunua zaidi',
                  'wanaonunua zaidi', 'wateja wanaotumia zaidi']) => $this->topCustomersAnswer($business),

            $has(['how many customer', 'total customer', 'number of customer', 'customer count',
                  'active customer', 'how many clients',
                  'wateja wangapi', 'idadi ya wateja', 'jumla ya wateja',
                  'una wateja wangapi', 'ninaowateja', 'wateja wangu wangapi',
                  'nina wateja wangapi', 'wateja ni wangapi']) => $this->customerCountAnswer($business),

            $has(['customer debt', 'customers owe', 'overdue customer', 'credit customer',
                  'who owes me money', 'debtor', 'who owes me',
                  'madeni ya wateja', 'wateja wanaodaiwa', 'wateja hawajalipia',
                  'wanaonipigia deni', 'wanaochelewea kulipa', 'hawajalipia',
                  'deni la wateja', 'nani ananidai', 'wanaonidai',
                  'wateja wanaonikopa', 'wananidai']) => $this->debtorsAnswer($business),

            $has(['outstanding', 'receivable', 'who owes', 'owed to me', 'amount owed',
                  'unpaid invoice', 'total owed',
                  'ninastahili kupata', 'wanaodaiwa nami', 'fedha ninazodaiwa',
                  'malipo yanayokusanywa', 'ninastahili pesa', 'wanadaiwa',
                  'jumla ya madeni', 'madeni yote']) => $this->arAgingAnswer($business),

            // ── SUPPLIERS & PAYABLES ─────────────────────────────────────────

            $has(['supplier pay', 'vendor pay', 'supplier owed', 'pay supplier',
                  'supplier invoice', 'what do i owe supplier', 'owe my supplier',
                  'suppliers should i pay', 'suppliers to pay', 'which supplier',
                  'who should i pay', 'pay first',
                  'malipo ya muuzaji', 'malipo ya wasambazaji', 'ninawadai wasambazaji',
                  'ninapaswa kuwalipa', 'lipa wasambazaji', 'deni la wasambazaji',
                  'wasambazaji wananipigia deni', 'wasambazaji wananidai']) => $this->payablesAnswer($business),

            $has(['what we owe', 'bills due', 'accounts payable', 'we owe',
                  'outstanding bill', 'unpaid bill',
                  'bili zinazodaiwa', 'tunadaiwa', 'tunapaswa kulipa',
                  'madeni yetu', 'malipo tunayodaiwa', 'tunadaiwa nani']) => $this->apAgingAnswer($business),

            // ── EXPENSES ─────────────────────────────────────────────────────

            $has(['expense', 'expenses', 'costs', 'spending', 'what did i spend',
                  'how much did i spend', 'overhead',
                  'gharama', 'matumizi', 'gharama zangu', 'matumizi yangu',
                  'nimotumia kiasi gani', 'nimetumia ngapi', 'gharama za mwezi',
                  'matumizi ya mwezi', 'nimetumia pesa ngapi',
                  'ninatumia ngapi', 'malipo gani']) => $this->expensesAnswer($business),

            // ── SALES & REVENUE ──────────────────────────────────────────────

            $has(['sales today', 'today sales', "today's sale", 'revenue today',
                  'today revenue', 'how much today', 'what did i sell today',
                  'sold today', "today's revenue", 'profit today',
                  'mauzo ya leo', 'mapato ya leo', 'tuliuza leo', 'faida ya leo',
                  'nimeuza ngapi leo', 'leo niliuza', 'leo nimefanya', 'leo mauzo',
                  'leo nimeuza', 'leo mapato', 'leo faida', 'faida leo',
                  'nimepata leo', 'leo nimepata']) => $this->salesTodayAnswer($business),

            $has(['total sales', 'total revenue', 'how much have i sold', 'how much did i make',
                  'monthly sales', 'this month sales', 'month revenue', 'overall sales',
                  'sales this month',
                  'jumla ya mauzo', 'mauzo yote', 'mapato yote', 'kiasi cha mauzo',
                  'mauzo ya mwezi', 'mapato ya mwezi', 'nimeuza kiasi gani',
                  'mwezi huu mauzo', 'jumla mauzo', 'mwezi huu nimeuza',
                  'mwezi huu nimepata']) => $this->totalSalesAnswer($business),

            // Profit / P&L this month — catches "faida" alone as a broad trigger
            $has(['profit this month', 'revenue this month', 'this month profit',
                  'monthly profit', 'am i profitable', 'making profit',
                  'am i making money', 'making money', 'making a profit',
                  'faida mwezi huu', 'mapato ya mwezi huu', 'gharama mwezi huu',
                  'faida ya mwezi', 'hasara mwezi huu', 'ninafanya faida',
                  'nina faida', 'faida yangu', 'faida ni ngapi', 'kiasi gani faida',
                  'ninafanya hasara', 'nina hasara', 'faida au hasara',
                  'je ninafanya faida', 'labda ninafanya hasara']) => $this->monthlyPlAnswer($business),

            $has(['profit drop', 'profit declin', 'profit decreas', 'why profit down',
                  'revenue drop', 'revenue declin', 'why losing money', 'making a loss',
                  'faida imeshuka', 'faida imepungua', 'kwa nini faida imepungua',
                  'mapato yamepungua', 'tunafanya hasara', 'hasara inakua',
                  'kwa nini tunapoteza', 'faida inapungua']) => $this->profitExplanationAnswer($business),

            $has(['forecast', 'predict', 'next month', 'projection', 'expected revenue',
                  'revenue forecast', 'sales prediction',
                  'utabiri', 'matarajio', 'mwezi ujao', 'tabiri mauzo',
                  'natarajia nini', 'utabiri wa mauzo', 'kadirio la mauzo',
                  'nitauza kiasi gani', 'nitapata kiasi gani']) => $this->forecastAnswer($business),

            // ── FINANCE & GL ─────────────────────────────────────────────────

            $has(['cash', 'bank balance', 'liquid', 'money in bank', 'how much cash',
                  'available funds', 'cash on hand', 'how much money do i have',
                  'money available', 'available balance',
                  'pesa taslimu', 'benki', 'akiba ya benki', 'salio la benki',
                  'pesa nilizo nazo', 'fedha', 'salio la akaunti', 'pesa mfukoni',
                  'pesa zangu', 'kiasi cha pesa', 'nina pesa ngapi',
                  'pesa ziko wapi', 'pesa zangu ziko wapi', 'nazo pesa ngapi',
                  'ninazo pesa ngapi', 'nipo na pesa ngapi']) => $this->cashPositionAnswer($business),

            $has(['budget', 'over budget', 'variance', 'spending plan', 'within budget',
                  'bajeti', 'mpango wa matumizi', 'tumezidi bajeti',
                  'tofauti ya bajeti', 'bajeti imezidiwa', 'mpango wa pesa',
                  'tunaenda vipi bajeti', 'bajeti yetu']) => $this->budgetVarianceAnswer($business),

            $has(['asset', 'depreciation', 'fixed asset', 'equipment value',
                  'mali', 'vifaa vya biashara', 'kushuka kwa thamani',
                  'amortizesheni', 'thamani ya vifaa', 'mali zetu',
                  'vifaa vina thamani', 'thamani ya mali']) => $this->assetSummaryAnswer($business),

            $has(['payroll', 'salary', 'salaries', 'wages', 'employee pay', 'staff pay',
                  'staff cost', 'employee salary',
                  'mshahara', 'mishahara', 'malipo ya wafanyakazi',
                  'wafanyakazi wanastahili', 'lipa wafanyakazi', 'mishahara ya mwezi',
                  'malipo ya kazi', 'wafanyakazi wanalipwa ngapi',
                  'mshahara wa wafanyakazi', 'tumewahi kulipa mishahara']) => $this->payrollSummaryAnswer($business),

            // ── GENERAL ──────────────────────────────────────────────────────

            $has(['summary', 'overview', 'how am i doing', 'how is business', 'business health',
                  'report', 'general', 'dashboard', 'business status',
                  'is business good', 'is my business doing well',
                  'muhtasari', 'hali ya biashara', 'biashara inakwenda vipi',
                  'niambie kuhusu biashara', 'habari za biashara',
                  'biashara yangu inakuwaje', 'tunaendaje', 'biashara ikoje',
                  'biashara inakwenda vizuri', 'biashara yangu inakwenda',
                  'nipe muhtasari', 'nionyeshe hali', 'hali yangu']) => $this->summaryAnswer($business),

            // "leo" / "today" catch-all — after all specific intents
            $has(['today', 'leo']) => $this->salesTodayAnswer($business),

            $has(['price', 'pricing', 'price change', 'raise price', 'lower price',
                  'bei gani', 'niweke bei', 'gani bei nzuri', 'punguza bei',
                  'ongeza bei', 'badilisha bei', 'bei ya kuuza']) => [
                'answer' => $this->t(
                    "I can't safely recommend price changes without a defined margin policy.",
                    'Kubadilisha bei kunahitaji sera ya faida ambayo sijui.',
                ),
                'source' => 'declined',
            ],

            default => $this->fallback($business, $question),
        };
    }

    // ── Language helpers ──────────────────────────────────────────────────────

    /**
     * Detects whether the question is primarily Swahili.
     * One distinctive Swahili word is enough — these never appear in English.
     */
    private function detectLanguage(string $question): string
    {
        $swahiliMarkers = [
            // Common standalone question/answer words
            'leo', 'jana', 'nini', 'vipi', 'gani', 'ngapi', 'wapi', 'lini',
            'yote', 'zote', 'yetu', 'zetu', 'yangu', 'zangu', 'kwangu',
            // Business / domain words unique to Swahili
            'mauzo', 'bidhaa', 'thamani', 'hisa', 'stok', 'akiba',
            'wateja', 'mteja', 'wasambazaji', 'muuzaji',
            'faida', 'hasara', 'mapato', 'gharama', 'matumizi',
            'mwezi', 'wiki', 'pesa', 'fedha', 'benki', 'bajeti',
            'mshahara', 'mishahara', 'wafanyakazi',
            'biashara', 'muhtasari', 'hali', 'deni', 'madeni',
            'utabiri', 'matarajio', 'agiza', 'hifadhi',
            'mali', 'vifaa', 'kuisha', 'zinaisha', 'zimekwisha',
            'tunaendaje', 'inakuwaje', 'ninastahili', 'tunadaiwa',
            'zinauzwa', 'zinaoza', 'haziuzi', 'nimeuza', 'nimepata',
            'nimetumia', 'niliweza', 'ninahitaji', 'ninafanya',
        ];

        $q     = Str::lower($question);
        $words = preg_split('/[\s,\.?!;:]+/', $q, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            if (in_array($word, $swahiliMarkers, true)) {
                return 'sw';
            }
        }

        // Multi-word Swahili phrases
        foreach ([
            'ya leo', 'ya jana', 'ya mwezi', 'ya wiki', 'ya biashara',
            'ya hisa', 'ya wateja', 'ya fedha', 'ya benki', 'ya mauzo',
            'ya faida', 'ya gharama', 'kiasi gani', 'ni ngapi', 'ni nini',
        ] as $phrase) {
            if (str_contains($q, $phrase)) {
                return 'sw';
            }
        }

        return 'en';
    }

    /** Returns the English or Swahili string based on detected language. */
    private function t(string $en, string $sw): string
    {
        return $this->lang === 'sw' ? $sw : $en;
    }

    // ── Intent handlers ───────────────────────────────────────────────────────

    /** @return array{answer: string, source: string} */
    private function greetingAnswer(Business $business): array
    {
        $name = $business->name;

        return [
            'answer' => $this->t(
                "Hello! I'm your Business Assistant for {$name}. I can answer questions about your business — try asking:\n"
                    . "• Today's or yesterday's sales & profit\n"
                    . "• This week's or this month's revenue\n"
                    . "• Stock value, low stock, or expiring products\n"
                    . "• Top-selling products or top customers\n"
                    . "• Customer debts or supplier payments\n"
                    . "• Cash & bank position\n"
                    . "• Expenses, payroll, or budget\n"
                    . "What would you like to know?",
                "Habari! Mimi ni Msaidizi wa Biashara kwa {$name}. Ninaweza kujibu maswali kuhusu biashara yako — jaribu kuuliza:\n"
                    . "• Mauzo na faida ya leo au jana\n"
                    . "• Mapato ya wiki hii au mwezi huu\n"
                    . "• Thamani ya hisa, hisa ndogo, au bidhaa zinazoisha muda\n"
                    . "• Bidhaa zinazoongoza au wateja wakubwa\n"
                    . "• Madeni ya wateja au malipo ya wasambazaji\n"
                    . "• Salio la pesa na benki\n"
                    . "• Gharama, mishahara, au bajeti\n"
                    . "Ungependa kujua nini?",
            ),
            'source' => 'greeting',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function yesterdayAnswer(Business $business): array
    {
        $saleIds = Sale::query()
            ->where('business_id', $business->id)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereDate('created_at', Carbon::yesterday())
            ->pluck('id');

        $count   = $saleIds->count();
        $revenue = (float) Sale::query()->whereIn('id', $saleIds)->sum('total_amount');
        $profit  = $saleIds->isNotEmpty()
            ? (float) SaleItem::query()
                ->whereIn('sale_id', $saleIds)
                ->whereNotNull('unit_cost')
                ->selectRaw('SUM((unit_price - unit_cost) * quantity) as profit')
                ->value('profit')
            : 0.0;

        $rev = number_format($revenue, 2);
        $prf = number_format($profit, 2);

        if ($count === 0) {
            return [
                'answer' => $this->t(
                    'No completed sales were recorded yesterday.',
                    'Hakuna mauzo yaliyokamilika yaliyorekodiwa jana.',
                ),
                'source' => 'sales',
            ];
        }

        return [
            'answer' => $this->t(
                "Yesterday: {$count} sale(s), revenue {$rev}, profit {$prf}.",
                "Jana: mauzo {$count}, mapato {$rev}, faida {$prf}.",
            ),
            'source' => 'sales',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function thisWeekAnswer(Business $business): array
    {
        $from = Str::contains(Str::lower(request()->input('question', '')), ['last week', 'wiki iliyopita'])
            ? Carbon::now()->subWeek()->startOfWeek()
            : Carbon::now()->startOfWeek();
        $to = $from->copy()->endOfWeek();

        $saleIds = Sale::query()
            ->where('business_id', $business->id)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id');

        $count   = $saleIds->count();
        $revenue = (float) Sale::query()->whereIn('id', $saleIds)->sum('total_amount');
        $profit  = $saleIds->isNotEmpty()
            ? (float) SaleItem::query()
                ->whereIn('sale_id', $saleIds)
                ->whereNotNull('unit_cost')
                ->selectRaw('SUM((unit_price - unit_cost) * quantity) as profit')
                ->value('profit')
            : 0.0;

        $rev  = number_format($revenue, 2);
        $prf  = number_format($profit, 2);
        $label = $this->t(
            $from->isSameWeek(now()) ? 'This week' : 'Last week',
            $from->isSameWeek(now()) ? 'Wiki hii' : 'Wiki iliyopita',
        );

        if ($count === 0) {
            return [
                'answer' => $this->t(
                    "{$label}: no completed sales recorded.",
                    "{$label}: hakuna mauzo yaliyokamilika.",
                ),
                'source' => 'sales',
            ];
        }

        return [
            'answer' => $this->t(
                "{$label}: {$count} sale(s), revenue {$rev}, profit {$prf}.",
                "{$label}: mauzo {$count}, mapato {$rev}, faida {$prf}.",
            ),
            'source' => 'sales',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function expensesAnswer(Business $business): array
    {
        $from = Carbon::now()->startOfMonth()->toDateString();
        $to   = Carbon::now()->toDateString();

        $total = Expense::query()
            ->where('business_id', $business->id)
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        $byCategory = Expense::query()
            ->where('business_id', $business->id)
            ->whereBetween('expense_date', [$from, $to])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $formatted = number_format((float) $total, 2);

        if ((float) $total === 0.0) {
            return [
                'answer' => $this->t(
                    'No expenses recorded this month yet.',
                    'Hakuna gharama zilizorekodiwa mwezi huu bado.',
                ),
                'source' => 'accounting',
            ];
        }

        $breakdown = $byCategory->map(fn ($r) => "{$r->category}: " . number_format((float) $r->total, 2))->implode(', ');

        return [
            'answer' => $this->t(
                "Total expenses this month: {$formatted}. Top categories: {$breakdown}.",
                "Jumla ya gharama mwezi huu: {$formatted}. Makundi makubwa: {$breakdown}.",
            ),
            'source' => 'accounting',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function topCustomersAnswer(Business $business): array
    {
        $top = Sale::query()
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.business_id', $business->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.created_at', '>=', Carbon::now()->subDays(30))
            ->whereNotNull('sales.customer_id')
            ->selectRaw('customers.name, COUNT(sales.id) as order_count, SUM(sales.total_amount) as total_spent')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        if ($top->isEmpty()) {
            return [
                'answer' => $this->t(
                    'No customer sales recorded in the last 30 days.',
                    'Hakuna mauzo ya wateja yaliyorekodiwa katika siku 30 zilizopita.',
                ),
                'source' => 'sales',
            ];
        }

        $lines = $top->map(fn ($r) => "{$r->name} (" . number_format((float) $r->total_spent, 2) . ', ' . $r->order_count . ' ' . $this->t('orders', 'maagizo') . ')')->implode('; ');

        return [
            'answer' => $this->t(
                "Top customers (last 30 days): {$lines}.",
                "Wateja wakubwa (siku 30 zilizopita): {$lines}.",
            ),
            'source' => 'sales',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function reorderAnswer(Business $business): array
    {
        $items = Inventory::query()
            ->where('business_id', $business->id)
            ->where('quantity', '>', 0)
            ->where('quantity', '<', Inventory::LOW_STOCK_THRESHOLD)
            ->with('product:id,name')
            ->limit(10)
            ->get();

        if ($items->isEmpty()) {
            return [
                'answer' => $this->t(
                    'Nothing needs reordering right now — all stock levels are healthy.',
                    'Hakuna bidhaa zinazohitaji kuagizwa sasa hivi — viwango vya hisa viko sawa.',
                ),
                'source' => 'inventory',
            ];
        }

        $names = $items->pluck('product.name')->filter()->implode(', ');
        $count = $items->count();

        return [
            'answer' => $this->t(
                "{$count} product(s) are at or below their reorder level: {$names}.",
                "Bidhaa {$count} ziko au chini ya kiwango cha kuagiza: {$names}.",
            ),
            'source' => 'inventory',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function stockValueAnswer(Business $business): array
    {
        $row = Inventory::query()
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->where('inventories.business_id', $business->id)
            ->where('inventories.quantity', '>', 0)
            ->whereNotNull('products.cost_price')
            ->selectRaw('SUM(inventories.quantity * products.cost_price) as total_value, COUNT(*) as sku_count')
            ->first();

        $value = number_format((float) ($row?->total_value ?? 0), 2);
        $count = (int) ($row?->sku_count ?? 0);

        return [
            'answer' => $this->t(
                "Your current stock is valued at {$value} (at cost price) across {$count} SKU(s) with stock.",
                "Thamani ya hisa yako ya sasa ni {$value} (kwa bei ya gharama) kwa bidhaa {$count} zilizo na hisa.",
            ),
            'source' => 'inventory',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function expiryAnswer(Business $business): array
    {
        $expiringSoon = ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->count();

        $expired = ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now())
            ->count();

        if ($expiringSoon === 0 && $expired === 0) {
            return [
                'answer' => $this->t(
                    'No expiring or expired batches in your inventory.',
                    'Hakuna bidhaa zinazokwisha muda au zimekwisha muda katika hifadhi yako.',
                ),
                'source' => 'inventory',
            ];
        }

        $enParts = [];
        $swParts = [];

        if ($expired > 0) {
            $enParts[] = "{$expired} expired batch(es)";
            $swParts[] = "vifurushi {$expired} vimekwisha muda";
        }
        if ($expiringSoon > 0) {
            $enParts[] = "{$expiringSoon} batch(es) expiring within 30 days";
            $swParts[] = "vifurushi {$expiringSoon} vitakwisha muda ndani ya siku 30";
        }

        return [
            'answer' => $this->t(
                'Inventory alert: ' . implode(' and ', $enParts) . '. Go to Inventory → Stock to review.',
                'Tahadhari ya hisa: ' . implode(' na ', $swParts) . '. Nenda Bidhaa → Hisa kuangalia na kuondoa.',
            ),
            'source' => 'inventory',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function slowMoversAnswer(Business $business): array
    {
        $since = Carbon::now()->subDays(30);

        $recentlySoldProductIds = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $business->id)
            ->where('sales.created_at', '>=', $since)
            ->pluck('sale_items.product_id')
            ->unique();

        $slowMovers = Inventory::query()
            ->where('business_id', $business->id)
            ->where('quantity', '>', 0)
            ->whereNotIn('product_id', $recentlySoldProductIds)
            ->with('product:id,name')
            ->limit(10)
            ->get();

        if ($slowMovers->isEmpty()) {
            return [
                'answer' => $this->t(
                    'Every product in stock has sold at least once in the last 30 days.',
                    'Bidhaa zote zilizo na hisa zimeuzwa angalau mara moja katika siku 30 zilizopita.',
                ),
                'source' => 'inventory+sales',
            ];
        }

        $names = $slowMovers->pluck('product.name')->filter()->implode(', ');
        $count = $slowMovers->count();

        return [
            'answer' => $this->t(
                "{$count} product(s) in stock haven't sold in 30 days: {$names}.",
                "Bidhaa {$count} zilizo na hisa hazijauzwa kwa siku 30: {$names}.",
            ),
            'source' => 'inventory+sales',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function topProductsAnswer(Business $business): array
    {
        $top = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.business_id', $business->id)
            ->where('sales.created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('products.name, SUM(sale_items.quantity) as total_qty, SUM(sale_items.total_price) as total_revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        if ($top->isEmpty()) {
            return [
                'answer' => $this->t(
                    'No sales recorded in the last 30 days yet.',
                    'Hakuna mauzo yaliyorekodiwa katika siku 30 zilizopita bado.',
                ),
                'source' => 'sales',
            ];
        }

        $lines = $top->map(fn ($r) => "{$r->name} ({$r->total_qty} " . $this->t('units', 'vipande') . ", {$r->total_revenue})")->implode('; ');

        return [
            'answer' => $this->t(
                "Top-selling products (last 30 days): {$lines}.",
                "Bidhaa zinazoongoza mauzo (siku 30 zilizopita): {$lines}.",
            ),
            'source' => 'sales',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function productCountAnswer(Business $business): array
    {
        $total   = Product::query()->where('business_id', $business->id)->count();
        $inStock = Inventory::query()->where('business_id', $business->id)->where('quantity', '>', 0)->count();

        return [
            'answer' => $this->t(
                "You have {$total} product(s) in your catalog; {$inStock} currently have stock.",
                "Una bidhaa {$total} kwenye orodha yako; {$inStock} zina hisa sasa hivi.",
            ),
            'source' => 'inventory',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function customerCountAnswer(Business $business): array
    {
        $total  = Customer::query()->where('business_id', $business->id)->count();
        $active = Customer::query()->where('business_id', $business->id)
            ->whereHas('sales', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))
            ->count();

        return [
            'answer' => $this->t(
                "You have {$total} customer(s) total; {$active} purchased in the last 30 days.",
                "Una wateja {$total} kwa jumla; {$active} walinunua katika siku 30 zilizopita.",
            ),
            'source' => 'sales',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function debtorsAnswer(Business $business): array
    {
        $debtors = Customer::query()
            ->where('business_id', $business->id)
            ->where('current_balance', '>', 0)
            ->orderByDesc('current_balance')
            ->limit(5)
            ->get(['name', 'current_balance']);

        if ($debtors->isEmpty()) {
            return [
                'answer' => $this->t(
                    'No customers currently owe you money.',
                    'Hakuna mteja anayedaiwa sasa hivi.',
                ),
                'source' => 'sales',
            ];
        }

        $lines = $debtors->map(fn ($c) => "{$c->name} ({$c->current_balance})")->implode(', ');

        return [
            'answer' => $this->t(
                "Top outstanding debtors: {$lines}.",
                "Wadai wakubwa: {$lines}.",
            ),
            'source' => 'sales',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function salesTodayAnswer(Business $business): array
    {
        $s = $this->salesDashboard->summary($business->id);

        return [
            'answer' => $this->t(
                "Today: {$s['today_sales_count']} sale(s), revenue {$s['today_revenue']}, profit {$s['today_profit']}.",
                "Leo: mauzo {$s['today_sales_count']}, mapato {$s['today_revenue']}, faida {$s['today_profit']}.",
            ),
            'source' => 'sales',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function totalSalesAnswer(Business $business): array
    {
        $s = $this->salesDashboard->summary($business->id);

        return [
            'answer' => $this->t(
                "This month: {$s['month_revenue']} revenue from {$s['month_sales_count']} sale(s) with {$s['month_profit']} profit. Today: {$s['today_revenue']}.",
                "Mwezi huu: mapato ya {$s['month_revenue']} kutoka mauzo {$s['month_sales_count']} na faida ya {$s['month_profit']}. Leo: {$s['today_revenue']}.",
            ),
            'source' => 'sales',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function forecastAnswer(Business $business): array
    {
        $trend   = $this->salesDashboard->revenueTrend($business->id);
        $amounts = array_column($trend, 'amount');
        $daysWithData = count(array_filter($amounts, fn ($a) => $a > 0));

        if ($daysWithData < 3) {
            return [
                'answer' => $this->t(
                    'Not enough sales history to forecast yet — keep recording sales.',
                    'Historia ya mauzo haitoshi kwa utabiri bado — endelea kurekodi mauzo.',
                ),
                'source' => 'declined',
            ];
        }

        $n    = count($amounts);
        $avgX = ($n - 1) / 2;
        $avgY = array_sum($amounts) / $n;
        $num  = 0.0;
        $den  = 0.0;

        foreach ($amounts as $x => $y) {
            $num += ($x - $avgX) * ($y - $avgY);
            $den += ($x - $avgX) ** 2;
        }

        $slope     = $den > 0 ? $num / $den : 0.0;
        $daily     = $avgY + $slope * (($n - 1) - $avgX);
        $projected = number_format(max(0, $daily) * 30, 2);

        return [
            'answer' => $this->t(
                "Based on your last 14 days, projected next-month revenue is ~{$projected} (rough estimate only).",
                "Kulingana na siku 14 zilizopita, mapato yanayotarajiwa mwezi ujao ni ~{$projected} (kadirio tu).",
            ),
            'source' => 'sales (linear trend)',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function profitExplanationAnswer(Business $business): array
    {
        $health = $this->businessHealth->compute($business);

        if (empty($health['recommendations']) || $health['recommendations'][0] === 'Everything looks healthy — keep up the great work!') {
            return [
                'answer' => $this->t(
                    'Numbers look healthy — no clear sign of declining profit.',
                    'Nambari zinaonekana nzuri — hakuna ishara ya kupungua kwa faida.',
                ),
                'source' => 'business-health',
            ];
        }

        return [
            'answer' => $this->t('Real signals suggest: ', 'Ishara halisi zinaonyesha: ')
                . implode(' ', $health['recommendations']),
            'source' => 'business-health',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function summaryAnswer(Business $business): array
    {
        $sales  = $this->salesDashboard->summary($business->id);
        $health = $this->businessHealth->compute($business);
        $recs   = implode(' ', $health['recommendations']);

        return [
            'answer' => $this->t(
                "Today: {$sales['today_sales_count']} sale(s), {$sales['today_revenue']} revenue, {$sales['today_profit']} profit. "
                    . "This month: {$sales['month_revenue']} revenue. {$recs}",
                "Leo: mauzo {$sales['today_sales_count']}, mapato {$sales['today_revenue']}, faida {$sales['today_profit']}. "
                    . "Mwezi huu: mapato {$sales['month_revenue']}. {$recs}",
            ),
            'source' => 'sales+business-health',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function payablesAnswer(Business $business): array
    {
        $payables = Expense::query()
            ->where('business_id', $business->id)
            ->where('status', Expense::STATUS_APPROVED)
            ->whereNotNull('supplier_id')
            ->with('supplier:id,name')
            ->selectRaw('supplier_id, sum(amount) as total')
            ->groupBy('supplier_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        if ($payables->isEmpty()) {
            return [
                'answer' => $this->t(
                    'No approved unpaid supplier expenses right now.',
                    'Hakuna gharama za wasambazaji zilizoidhinishwa na hazijalipwa sasa hivi.',
                ),
                'source' => 'accounting',
            ];
        }

        $lines = $payables->map(fn ($row) => "{$row->supplier?->name} ({$row->total})")->implode(', ');

        return [
            'answer' => $this->t(
                "Suppliers to pay (largest first): {$lines}.",
                "Wasambazaji wa kulipa (wakubwa kwanza): {$lines}.",
            ),
            'source' => 'accounting',
        ];
    }

    /** @return array{answer: string, source: string} */
    private function cashPositionAnswer(Business $business): array
    {
        try {
            $summary = $this->financeDashboard->summary($business->id);

            if (! $summary) {
                return [
                    'answer' => $this->t(
                        'Chart of Accounts not set up yet.',
                        'Mpango wa akaunti bado haujawekwa.',
                    ),
                    'source' => 'finance-gl',
                ];
            }

            $total = bcadd((string) $summary['cash']['value'], (string) $summary['bank']['value'], 2);

            return [
                'answer' => $this->t(
                    "Liquid position — Cash: {$summary['cash']['value']}, Bank: {$summary['bank']['value']}, Combined: {$total}.",
                    "Hali ya pesa — Taslimu: {$summary['cash']['value']}, Benki: {$summary['bank']['value']}, Jumla: {$total}.",
                ),
                'source' => 'finance-gl',
            ];
        } catch (\Throwable) {
            return [
                'answer' => $this->t('Finance GL data not available yet.', 'Data ya fedha bado haipatikani.'),
                'source' => 'declined',
            ];
        }
    }

    /** @return array{answer: string, source: string} */
    private function arAgingAnswer(Business $business): array
    {
        try {
            $ar      = $this->chartOfAccounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_ACCOUNTS_RECEIVABLE);
            $balance = $this->generalLedger->accountBalance($ar);

            if (bccomp($balance, '0', 2) <= 0) {
                return [
                    'answer' => $this->t(
                        'No outstanding accounts receivable — all invoices settled.',
                        'Hakuna madeni ya wateja yanayosubiri — ankara zote zimelipwa.',
                    ),
                    'source' => 'finance-gl',
                ];
            }

            $topDebtors = Customer::query()
                ->where('business_id', $business->id)
                ->where('current_balance', '>', 0)
                ->orderByDesc('current_balance')
                ->limit(5)
                ->get(['name', 'current_balance']);

            $names = $topDebtors->map(fn ($c) => "{$c->name} ({$c->current_balance})")->implode(', ');

            return [
                'answer' => $this->t(
                    "Total receivables: {$balance}. Top outstanding: {$names}.",
                    "Jumla ya madeni yanayodaiwa: {$balance}. Wakubwa zaidi: {$names}.",
                ),
                'source' => 'finance-gl+sales',
            ];
        } catch (\Throwable) {
            return [
                'answer' => $this->t('Finance GL data not available yet.', 'Data ya fedha bado haipatikani.'),
                'source' => 'declined',
            ];
        }
    }

    /** @return array{answer: string, source: string} */
    private function apAgingAnswer(Business $business): array
    {
        try {
            $ap      = $this->chartOfAccounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_ACCOUNTS_PAYABLE);
            $balance = $this->generalLedger->accountBalance($ap);

            if (bccomp($balance, '0', 2) <= 0) {
                return [
                    'answer' => $this->t(
                        'No outstanding accounts payable — all supplier bills settled.',
                        'Hakuna bili za wasambazaji zinazodaiwa — bili zote zimelipwa.',
                    ),
                    'source' => 'finance-gl',
                ];
            }

            return [
                'answer' => $this->t(
                    "Accounts payable (what you owe suppliers): {$balance}.",
                    "Malipo yanayodaiwa kwa wasambazaji: {$balance}.",
                ),
                'source' => 'finance-gl',
            ];
        } catch (\Throwable) {
            return [
                'answer' => $this->t('Finance GL data not available yet.', 'Data ya fedha bado haipatikani.'),
                'source' => 'declined',
            ];
        }
    }

    /** @return array{answer: string, source: string} */
    private function monthlyPlAnswer(Business $business): array
    {
        try {
            $data = $this->financialStatements->profitAndLoss(
                $business->id,
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->toDateString(),
            );

            $profitable = bccomp($data['net_profit'], '0', 2) >= 0;
            $status     = $this->t($profitable ? 'profit' : 'loss', $profitable ? 'faida' : 'hasara');

            return [
                'answer' => $this->t(
                    "This month so far — Revenue: {$data['total_revenue']}, Expenses: {$data['total_expenses']}, Net {$status}: {$data['net_profit']}.",
                    "Mwezi huu hadi sasa — Mapato: {$data['total_revenue']}, Gharama: {$data['total_expenses']}, {$status} halisi: {$data['net_profit']}.",
                ),
                'source' => 'finance-gl',
            ];
        } catch (\Throwable) {
            // GL not set up — fall back to sales-based gross profit
            $s = $this->salesDashboard->summary($business->id);

            return [
                'answer' => $this->t(
                    "This month: {$s['month_revenue']} revenue, {$s['month_profit']} gross profit (based on sales; full GL not configured).",
                    "Mwezi huu: mapato {$s['month_revenue']}, faida ghafi {$s['month_profit']} (kulingana na mauzo; GL kamili haijawekwa).",
                ),
                'source' => 'sales',
            ];
        }
    }

    /** @return array{answer: string, source: string} */
    private function budgetVarianceAnswer(Business $business): array
    {
        try {
            $activeBudget = $this->budgetService->activeBudget($business->id, (int) now()->format('Y'));

            if (! $activeBudget) {
                return [
                    'answer' => $this->t(
                        'No active budget for this fiscal year. Set one up under Finance → Budgets.',
                        'Hakuna bajeti inayotumika mwaka huu wa fedha. Weka moja chini ya Fedha → Bajeti.',
                    ),
                    'source' => 'finance-budget',
                ];
            }

            $vsActual   = $this->budgetService->budgetVsActual($activeBudget);
            $overBudget = array_filter($vsActual, fn ($row) => bccomp($row['variance'], '0', 2) < 0);

            if (empty($overBudget)) {
                return [
                    'answer' => $this->t(
                        "Budget \"{$activeBudget->name}\": all accounts within budget.",
                        "Bajeti \"{$activeBudget->name}\": akaunti zote ziko ndani ya bajeti.",
                    ),
                    'source' => 'finance-budget',
                ];
            }

            $lines = array_map(
                fn ($row) => "{$row['account_name']}: {$row['variance']} ({$row['variance_pct']}%)",
                array_slice(array_values($overBudget), 0, 5),
            );
            $count = count($overBudget);

            return [
                'answer' => $this->t(
                    "Budget \"{$activeBudget->name}\" — {$count} account(s) over budget: " . implode('; ', $lines) . '.',
                    "Bajeti \"{$activeBudget->name}\" — Akaunti {$count} zimezidi bajeti: " . implode('; ', $lines) . '.',
                ),
                'source' => 'finance-budget',
            ];
        } catch (\Throwable) {
            return [
                'answer' => $this->t('Budget data not available yet.', 'Data ya bajeti bado haipatikani.'),
                'source' => 'declined',
            ];
        }
    }

    /** @return array{answer: string, source: string} */
    private function assetSummaryAnswer(Business $business): array
    {
        try {
            $pendingCount = DepreciationSchedule::query()
                ->join('fixed_assets', 'fixed_assets.id', '=', 'depreciation_schedules.fixed_asset_id')
                ->where('fixed_assets.business_id', $business->id)
                ->where('depreciation_schedules.status', 'pending')
                ->where('depreciation_schedules.period_date', '<=', now()->format('Y-m-01'))
                ->count();

            if ($pendingCount === 0) {
                return [
                    'answer' => $this->t(
                        'All asset depreciation is up to date.',
                        'Upungufu wa thamani wa mali wote umekamilika.',
                    ),
                    'source' => 'finance-assets',
                ];
            }

            return [
                'answer' => $this->t(
                    "{$pendingCount} depreciation entry(ies) pending. Go to Finance → Assets → Post Depreciation.",
                    "Ingizo {$pendingCount} la upungufu wa thamani linasubiri. Nenda Fedha → Mali → Chapisha Upungufu.",
                ),
                'source' => 'finance-assets',
            ];
        } catch (\Throwable) {
            return [
                'answer' => $this->t('Asset data not available yet.', 'Data ya mali bado haipatikani.'),
                'source' => 'declined',
            ];
        }
    }

    /** @return array{answer: string, source: string} */
    private function payrollSummaryAnswer(Business $business): array
    {
        try {
            $lastPaid = PayrollPeriod::query()
                ->where('business_id', $business->id)
                ->where('status', PayrollPeriod::STATUS_PAID)
                ->orderByDesc('paid_at')
                ->first(['period_name', 'total_gross', 'total_net', 'paid_at']);

            if (! $lastPaid) {
                $draft = PayrollPeriod::query()
                    ->where('business_id', $business->id)
                    ->where('status', '!=', PayrollPeriod::STATUS_PAID)
                    ->orderByDesc('period_start')
                    ->first(['period_name', 'status']);

                if ($draft) {
                    return [
                        'answer' => $this->t(
                            "No payroll paid yet. Period \"{$draft->period_name}\" is {$draft->status}.",
                            "Hakuna mshahara uliofanywa bado. Kipindi \"{$draft->period_name}\" kiko {$draft->status}.",
                        ),
                        'source' => 'payroll',
                    ];
                }

                return [
                    'answer' => $this->t(
                        'No payroll periods found. Set up under Payroll → Periods.',
                        'Hakuna vipindi vya mishahara vilivyopatikana. Weka chini ya Mishahara → Vipindi.',
                    ),
                    'source' => 'payroll',
                ];
            }

            $date = $lastPaid->paid_at->toFormattedDateString();

            return [
                'answer' => $this->t(
                    "Last payroll: \"{$lastPaid->period_name}\" paid {$date}. Gross: {$lastPaid->total_gross}, Net: {$lastPaid->total_net}.",
                    "Mishahara ya mwisho: \"{$lastPaid->period_name}\" ililipwa {$date}. Jumla kubwa: {$lastPaid->total_gross}, Halisi: {$lastPaid->total_net}.",
                ),
                'source' => 'payroll',
            ];
        } catch (\Throwable) {
            return [
                'answer' => $this->t('Payroll data not available yet.', 'Data ya mishahara bado haipatikani.'),
                'source' => 'declined',
            ];
        }
    }

    // ── Fallback: OpenAI with rich business context ────────────────────────────

    /** @return array{answer: string, source: string} */
    private function fallback(Business $business, string $question): array
    {
        $context   = $this->buildBusinessContext($business);
        $narrative = $this->narrative->summarize($question, $context);

        if ($narrative !== null) {
            return ['answer' => $narrative, 'source' => 'ai-integration'];
        }

        return [
            'answer' => $this->t(
                "I don't have a direct answer for that. Try asking about: today's sales, yesterday's sales, this week's sales, profit this month, top products, stock value, expiring stock, customer debts, expenses, cash position, payroll, or budget.",
                "Sijui jibu la swali hilo moja kwa moja. Jaribu kuuliza kuhusu: mauzo ya leo, mauzo ya jana, mauzo ya wiki hii, faida ya mwezi huu, bidhaa zinazoongoza, thamani ya hisa, bidhaa zinazoisha muda, madeni ya wateja, gharama, salio la pesa, mishahara, au bajeti.",
            ),
            'source' => 'declined',
        ];
    }

    /** @return array<string, mixed> */
    private function buildBusinessContext(Business $business): array
    {
        $context = ['business_name' => $business->name, 'date' => now()->toDateString()];

        try {
            $s               = $this->salesDashboard->summary($business->id);
            $context['sales'] = [
                'today_sales_count'  => $s['today_sales_count'] ?? 0,
                'today_revenue'      => $s['today_revenue'] ?? 0,
                'today_profit'       => $s['today_profit'] ?? 0,
                'month_sales_count'  => $s['month_sales_count'] ?? 0,
                'month_revenue'      => $s['month_revenue'] ?? 0,
                'month_profit'       => $s['month_profit'] ?? 0,
                'outstanding_credit' => $s['outstanding_credit'] ?? 0,
            ];
        } catch (\Throwable) {}

        try {
            $ySaleIds  = Sale::query()->where('business_id', $business->id)->where('status', Sale::STATUS_COMPLETED)->whereDate('created_at', Carbon::yesterday())->pluck('id');
            $yRevenue  = (float) Sale::query()->whereIn('id', $ySaleIds)->sum('total_amount');
            $yProfit   = $ySaleIds->isNotEmpty()
                ? (float) SaleItem::query()->whereIn('sale_id', $ySaleIds)->whereNotNull('unit_cost')->selectRaw('SUM((unit_price - unit_cost) * quantity) as profit')->value('profit')
                : 0.0;
            $context['yesterday'] = ['count' => $ySaleIds->count(), 'revenue' => $yRevenue, 'profit' => $yProfit];
        } catch (\Throwable) {}

        try {
            $row = Inventory::query()
                ->join('products', 'products.id', '=', 'inventories.product_id')
                ->where('inventories.business_id', $business->id)
                ->where('inventories.quantity', '>', 0)
                ->selectRaw('SUM(inventories.quantity * products.cost_price) as total_value, COUNT(*) as sku_count, SUM(inventories.quantity) as total_units')
                ->first();

            $lowStock = Inventory::query()->where('business_id', $business->id)->where('quantity', '>', 0)->where('quantity', '<', Inventory::LOW_STOCK_THRESHOLD)->count();

            $context['inventory'] = [
                'stock_value_at_cost' => round((float) ($row?->total_value ?? 0), 2),
                'skus_in_stock'       => (int) ($row?->sku_count ?? 0),
                'total_units'         => round((float) ($row?->total_units ?? 0), 0),
                'low_stock_count'     => $lowStock,
            ];
        } catch (\Throwable) {}

        try {
            $context['customers'] = [
                'total'      => Customer::query()->where('business_id', $business->id)->count(),
                'with_balance' => Customer::query()->where('business_id', $business->id)->where('current_balance', '>', 0)->count(),
                'total_owed' => (float) Customer::query()->where('business_id', $business->id)->sum('current_balance'),
            ];
        } catch (\Throwable) {}

        try {
            $finSummary = $this->financeDashboard->summary($business->id);
            if ($finSummary) {
                $context['finance'] = [
                    'cash_balance'        => $finSummary['cash']['value'] ?? 0,
                    'bank_balance'        => $finSummary['bank']['value'] ?? 0,
                    'accounts_receivable' => $finSummary['accounts_receivable']['value'] ?? 0,
                    'accounts_payable'    => $finSummary['accounts_payable']['value'] ?? 0,
                ];
            }
        } catch (\Throwable) {}

        try {
            $pl = $this->financialStatements->profitAndLoss($business->id, now()->startOfMonth()->toDateString(), now()->toDateString());
            $context['profit_and_loss_this_month'] = ['revenue' => $pl['total_revenue'], 'expenses' => $pl['total_expenses'], 'net_profit' => $pl['net_profit']];
        } catch (\Throwable) {}

        return $context;
    }
}
