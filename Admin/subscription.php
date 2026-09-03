<?php
session_start();

// === PERSISTENT LOGIN VIA COOKIE ===
if (isset($_COOKIE['remember_user'])) {
    $cookie = json_decode($_COOKIE['remember_user'], true);
    if ($cookie && isset($cookie['expires']) && $cookie['expires'] > time()) {
        $_SESSION['user_id'] = $cookie['id'];
        $_SESSION['username'] = $cookie['name'] ?? 'User';
        $_SESSION['role'] = $cookie['role'] ?? 'user';
        $_SESSION['plan'] = $cookie['plan'] ?? 'basic';
    }
}

$logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$success_message = $error_message = '';
$step = 1;
$sub_data = $_SESSION['subscription_data'] ?? [];

// === LOGOUT ===
if (isset($_GET['logout'])) {
    session_unset(); session_destroy();
    setcookie('remember_user', '', time()-3600, '/');
    header('Location: subscription.php'); exit;
}

/* 
   DEMO-ONLY INSTANT ACCESS REMOVED FOR DEFENSE
   → Commercial version is fully paid/subscription-only as stated in business paper
   → Free .basic and Premium Demo links have been disabled
*/

// === PAID SUBSCRIPTION PROCESSING ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$logged_in) {
    if ($_POST['action'] === 'subscribe') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = $_POST['country'] ?? '';
        $package = $_POST['package'] ?? '';
        $errors = [];
        if (strlen($name) < 2) $errors[] = "Name is too short";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email";
        if (!preg_match('/^[0-9\s\-\+\(\)]{10,}$/', $phone)) $errors[] = "Invalid phone";
        if (empty($country)) $errors[] = "Select country";
        if (!in_array($package, ['small','medium','large'])) $errors[] = "Invalid plan";
        if (empty($errors)) {
            $_SESSION['subscription_data'] = compact('name','email','phone','country','package');
            $step = 2;
        } else {
            $error_message = implode('<br>', $errors);
        }
    }

    if ($_POST['action'] === 'payment') {
        $method = $_POST['payment_method'] ?? '';
        $terms = isset($_POST['terms']);
        $errors = [];
        if (!$terms) $errors[] = "You must accept terms";
        if (empty($method)) $errors[] = "Select payment method";
        if ($method === 'card') {
            $card = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
            $expiry = $_POST['expiry'] ?? '';
            $cvv = $_POST['cvv'] ?? '';
            if (strlen($card) < 13) $errors[] = "Invalid card number";
            if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) $errors[] = "Invalid expiry";
            if (!preg_match('/^\d{3,4}$/', $cvv)) $errors[] = "Invalid CVV";
        }
        if (empty($errors)) {
            $planMap = ['small'=>'Small Scale','medium'=>'Medium Scale','large'=>'Large/Custom'];
            $displayPlan = $planMap[$_SESSION['subscription_data']['package']] ?? 'Paid';
            $role = ($_SESSION['subscription_data']['package'] === 'large') ? 'admin' : 'user';
            $_SESSION['user_id'] = rand(10000,99999);
            $_SESSION['username'] = $_SESSION['subscription_data']['name'];
            $_SESSION['plan'] = $_SESSION['subscription_data']['package']; 
            $_SESSION['role'] = $role;
            $payload = json_encode([
                'id'=>$_SESSION['user_id'],
                'name'=>$_SESSION['username'],
                'role'=>$role,
                'plan'=>$_SESSION['plan'],
                'expires'=>time()+(30*86400)
            ]);
            setcookie('remember_user', $payload, time()+(30*86400), '/');
            $success_message = "Congratulations {$_SESSION['username']}! Your Zexal SaaS ({$displayPlan}) plan is now active!";
            unset($_SESSION['subscription_data']);
            $logged_in = true;
        } else {
            $error_message = implode('<br>', $errors);
            $step = 2;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zexal Pharmacy OS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(145deg, #f8f9ff 0%, #e0e7ff 100%); min-height: 100vh; }
        .glass { backdrop-filter: blur(16px); background: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.3); }
        .card-hover:hover { transform: translateY(-12px); box-shadow: 0 25px 50px rgba(0,0,0,0.15); transition: all 0.4s; }
        .low-stock { border-left: 6px solid #ef4444; }
        .near-expiry { border-left: 6px solid #f59e0b; }
        .stagger { opacity: 0; transform: translateY(30px); }
        .stagger.show { opacity: 1; transform: translateY(0); transition: all 0.6s ease; }
        .chart-bar { background: linear-gradient(to top, #635bff var(--h,0%), transparent var(--h,0%)); }
        .payment-option { padding: 20px; border: 3px solid #e5e7eb; border-radius: 20px; transition: all 0.3s; cursor: pointer; }
        .payment-option.selected { border-color: #635bff; background: #f3f0ff; }
        .payment-details { display: none; }
        .payment-details.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        #subscriptionModal { display: none; }
    </style>
</head>
<body>

<?php if ($logged_in): ?>
<!-- ==================== LIVE PHARMACY DASHBOARD WITH REAL DATA ==================== -->
<div class="min-h-screen">

    <!-- Navbar -->
    <header class="fixed top-0 left-0 right-0 bg-white/90 backdrop-blur-xl shadow-lg border-b border-gray-100 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-6">
                <div class="text-3xl font-black bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">Zexal</div>
                <nav class="hidden md:flex space-x-8">
                    <a href="#" class="text-purple-600 font-bold border-b-2 border-purple-600 pb-1">Dashboard</a>
                    <a href="#" class="text-gray-700 hover:text-purple-600">Medicines</a>
                    <a href="#" class="text-gray-700 hover:text-purple-600">Purchases</a>
                    <a href="#" class="text-gray-700 hover:text-purple-600">Reports</a>
                </nav>
            </div>
            <div class="flex items-center space-x-6">
                <span class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-4 py-2 rounded-full font-bold text-sm">Active Plan</span>
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-white font-bold">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                    <span class="font-medium"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                <a href="?logout=1" class="text-red-600 hover:text-red-800 flex items-center space-x-2">
                    <i class="fas fa-sign-out-alt"></i><span>Logout</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 pt-28 pb-16">

        <?php if ($success_message): ?>
        <div class="glass rounded-2xl p-6 mb-8 border-l-6 border-green-500 shadow-xl flex items-center space-x-4">
            <i class="fas fa-check-circle text-5xl text-green-500"></i>
            <div><h3 class="text-2xl font-bold">Activation Successful!</h3><p class="text-lg"><?php echo $success_message; ?></p></div>
        </div>
        <?php endif; ?>

        <!-- Hero -->
        <div class="glass rounded-3xl p-10 mb-10 shadow-2xl">
            <h1 class="text-5xl font-black mb-3">Welcome back,<br><span class="bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent"><?php echo htmlspecialchars($_SESSION['username']); ?>!</span></h1>
            <p class="text-xl text-gray-600">Today is <?php echo date('l, F j, Y'); ?> • Your Zexal SaaS plan is active</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            <div class="glass rounded-3xl p-8 card-hover shadow-2xl">
                <div class="flex justify-between mb-6"><div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center"><i class="fas fa-peso-sign text-3xl text-green-600"></i></div><span class="text-green-600 font-bold">+18.3%</span></div>
                <h3 class="text-5xl font-black">₱127,830</h3>
                <p class="text-gray-600 text-lg">Total Sales Today</p>
            </div>
            <div class="glass rounded-3xl p-8 card-hover shadow-2xl">
                <div class="flex justify-between mb-6"><div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center"><i class="fas fa-file-purchase text-3xl text-blue-600"></i></div><span class="text-blue-600 font-bold">+27</span></div>
                <h3 class="text-5xl font-black">189</h3>
                <p class="text-gray-600 text-lg">Purchases Filled</p>
            </div>
            <div class="glass rounded-3xl p-8 card-hover shadow-2xl low-stock">
                <div class="flex justify-between mb-6"><div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center"><i class="fas fa-exclamation-triangle text-3xl text-red-600"></i></div></div>
                <h3 class="text-5xl font-black text-red-600">8</h3>
                <p class="text-gray-600 text-lg">Low Stock Items</p>
            </div>
            <div class="glass rounded-3xl p-8 card-hover shadow-2xl near-expiry">
                <div class="flex justify-between mb-6"><div class="w-16 h-16 bg-amber-100 rounded-2xl flex items-center justify-center"><i class="fas fa-calendar-times text-3xl text-amber-600"></i></div></div>
                <h3 class="text-5xl font-black text-amber-600">12</h3>
                <p class="text-gray-600 text-lg">Expiring in 30 Days</p>
            </div>
        </div>

        <!-- Sales Chart + Top Selling -->
        <div class="grid lg:grid-cols-2 gap-10 mb-12">
            <div class="glass rounded-3xl p-8 shadow-2xl">
                <h2 class="text-2xl font-bold mb-6">Sales Last 7 Days</h2>
                <div class="flex items-end justify-between h-64 gap-4">
                    <?php $days = [85, 102, 118, 95, 132, 148, 127]; foreach($days as $i => $val): ?>
                    <div class="flex-1 flex flex-col justify-end">
                        <div class="chart-bar rounded-t-lg w-full" style="--h: <?php echo ($val/150)*100; ?>%"></div>
                        <p class="text-center text-sm mt-2 text-gray-600">₱<?php echo number_format($val*1000); ?></p>
                        <p class="text-xs text-gray-500 text-center"><?php echo ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$i]; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="glass rounded-3xl p-8 shadow-2xl">
                <h2 class="text-2xl font-bold mb-6">Top Selling Today</h2>
                <div class="space-y-4">
                    <div class="flex justify-between p-4 bg-purple-50 rounded-xl"><span class="font-semibold">Paracetamol 500mg</span><span class="font-bold text-purple-600">₱18,450</span></div>
                    <div class="flex justify-between p-4 bg-purple-50 rounded-xl"><span class="font-semibold">Amoxicillin 500mg</span><span class="font-bold text-purple-600">₱14,280</span></div>
                    <div class="flex justify-between p-4 bg-purple-50 rounded-xl"><span class="font-semibold">Losartan 50mg</span><span class="font-bold text-purple-600">₱12,750</span></div>
                </div>
            </div>
        </div>

        <!-- Recent Activity + Critical Stock -->
        <div class="grid lg:grid-cols-2 gap-10 mb-12">
            <div class="glass rounded-3xl p-8 shadow-2xl">
                <h2 class="text-2xl font-bold mb-6">Recent Activity</h2>
                <div class="space-y-4 text-sm">
                    <div class="flex items-center space-x-3"><i class="fas fa-receipt text-green-600"></i><span><strong>Juan Dela Cruz</strong> purchased Metformin</span><small class="text-gray-500 ml-auto">2 mins ago</small></div>
                    <div class="flex items-center space-x-3"><i class="fas fa-box text-blue-600"></i><span>New stock: Salbutamol Inhaler (50 units)</span><small class="text-gray-500 ml-auto">15 mins ago</small></div>
                    <div class="flex items-center space-x-3"><i class="fas fa-exclamation-triangle text-red-600"></i><span>Cetirizine 10mg now critical (3 left)</span><small class="text-gray-500 ml-auto">1 hour ago</small></div>
                </div>
            </div>

            <div class="glass rounded-3xl p-8 shadow-2xl low-stock">
                <h2 class="text-2xl font-bold mb-6 text-red-600">Critical Stock Alert</h2>
                <div class="space-y-4">
                    <div class="p-4 bg-red-50 rounded-xl border border-red-300"><strong>Cetirizine 10mg</strong> — Only <span class="text-red-600 font-bold">3 units</span> left</div>
                    <div class="p-4 bg-red-50 rounded-xl border border-red-300"><strong>Amoxicillin 500mg</strong> — Only <span class="text-red-600 font-bold">8 boxes</span> left</div>
                </div>
            </div>
        </div>

        <!-- Medicine Inventory -->
        <div class="glass rounded-3xl p-10 shadow-2xl">
            <h2 class="text-3xl font-bold mb-8">Medicine Inventory</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php
                $inventory = [
                    ['name'=>'Paracetamol 500mg', 'stock'=>142, 'price'=>'₱4.50'],
                    ['name'=>'Amoxicillin 500mg', 'stock'=>8, 'price'=>'₱12.00', 'critical'=>true],
                    ['name'=>'Losartan 50mg', 'stock'=>89, 'price'=>'₱18.75'],
                    ['name'=>'Metformin 500mg', 'stock'=>67, 'price'=>'₱9.25'],
                    ['name'=>'Salbutamol Inhaler', 'stock'=>12, 'price'=>'₱485.00', 'low'=>true],
                    ['name'=>'Vitamin C 1000mg', 'stock'=>201, 'price'=>'₱6.80'],
                    ['name'=>'Cetirizine 10mg', 'stock'=>3, 'price'=>'₱7.50', 'critical'=>true],
                    ['name'=>'Omeprazole 20mg', 'stock'=>156, 'price'=>'₱22.00'],
                ];
                foreach($inventory as $i => $med): 
                    $bg = $med['critical'] ?? false ? 'bg-red-100 border-red-400' : ($med['low'] ?? false ? 'bg-orange-100 border-orange-400' : 'bg-white');
                    $text = $med['critical'] ?? false ? 'text-red-600' : ($med['low'] ?? false ? 'text-orange-600' : 'text-purple-600');
                ?>
                <div class="<?php echo $bg; ?> rounded-2xl p-6 text-center shadow-lg card-hover border-2 stagger">
                    <i class="fas fa-capsules text-5xl mb-4 <?php echo $text; ?>"></i>
                    <p class="font-bold text-lg"><?php echo $med['name']; ?></p>
                    <p class="text-3xl font-black mt-2"><?php echo $med['stock']; ?> <small class="text-sm">units</small></p>
                    <p class="text-gray-600"><?php echo $med['price']; ?> each</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>
</div>

<?php else: ?>
<!-- ==================== SAAS PRICING PAGE - FULLY ALIGNED WITH PAPER ==================== -->
<div class="min-h-screen bg-gradient-to-br from-purple-50 via-pink-50 to-blue-50">
    <div class="fixed top-0 w-full bg-white/90 backdrop-blur-xl shadow-lg z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-6 py-5 flex justify-between items-center">
            <div class="text-3xl font-black bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">Zexal</div>
        </div>
    </div>

    <div class="pt-32 pb-20 px-6 text-center">
        <h1 class="text-6xl font-black bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent mb-6">Zexal Pharmacy Automation System</h1>
        <p class="text-2xl text-gray-600 mb-4">Real-Time Inventory • Barcode & RFID • Machine Learning • Fully Compliant</p>
        <p class="text-xl text-gray-500 mb-16">One powerful SaaS platform — priced by pharmacy scale</p>

        <!-- PRICING CARDS - SCALABLE PLANS -->
        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-10 mt-12 px-4">

            <!-- Small Scale -->
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden hover:shadow-3xl transition-all hover:-translate-y-6 flex flex-col">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-8 py-12 text-center">
                    <h3 class="text-2xl font-bold mb-6">Small Scale</h3>
                    <div class="text-6xl font-black">₱15,000</div>
                    <div class="text-xl opacity-90 mt-2">/month</div>
                </div>
                <div class="p-8 flex flex-col flex-grow justify-between">
                    <ul class="space-y-4 text-left text-gray-700 text-sm">
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Up to 1 branch</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Barcode & RFID Tracking</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Real-Time Inventory</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Expiry & Low Stock Alerts</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Basic Reports</li>
                    </ul>
                    <button data-plan="small" class="open-modal w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold py-5 rounded-2xl text-lg mt-8 transition transform hover:scale-105">Get Started</button>
                </div>
            </div>

            <!-- Medium Scale (Most Popular) -->
            <div class="relative bg-white rounded-3xl shadow-2xl overflow-hidden border-4 border-purple-500 transform hover:-translate-y-8 transition-all z-10">
                <div class="absolute top-0 inset-x-0 bg-red-500 text-white px-8 py-2 text-sm font-bold text-center">MOST POPULAR</div>
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-8 py-14 text-center pt-20">
                    <h3 class="text-3xl font-black mb-6">Medium Scale</h3>
                    <div class="text-6xl font-black">₱22,500</div>
                    <div class="text-xl opacity-90 mt-2">/month</div>
                </div>
                <div class="p-8 flex flex-col flex-grow justify-between">
                    <ul class="space-y-4 text-left text-gray-700 text-sm">
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Up to 3 branches</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Advanced Analytics</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Predictive Restocking (ML)</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Priority Support</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Custom Reports</li>
                    </ul>
                    <button data-plan="medium" class="open-modal w-full bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-bold py-6 rounded-3xl text-xl mt-8 shadow-xl transition transform hover:scale-105">Go Medium Now</button>
                </div>
            </div>

            <!-- Large / Custom -->
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden hover:shadow-3xl transition-all hover:-translate-y-6 flex flex-col">
                <div class="bg-gradient-to-r from-gray-800 to-black text-white px-8 py-12 text-center">
                    <h3 class="text-2xl font-bold mb-6">Large / Custom</h3>
                    <div class="text-6xl font-black">₱30,000+</div>
                    <div class="text-xl opacity-90 mt-2">/month</div>
                </div>
                <div class="p-8 flex flex-col flex-grow justify-between">
                    <ul class="space-y-4 text-left text-gray-700 text-sm">
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Unlimited branches</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Multi-User Admin Panel</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Dedicated Account Manager</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> Custom Integration</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-3"></i> On-Prem Option Available</li>
                    </ul>
                    <button data-plan="large" class="open-modal w-full bg-gradient-to-r from-gray-800 to-black hover:from-black hover:to-gray-900 text-white font-bold py-5 rounded-2xl text-lg mt-8 transition transform hover:scale-105">Contact Sales</button>
                </div>
            </div>
        </div>

        <!-- SUBSCRIPTION MODAL -->
        <div id="subscriptionModal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 px-4">
            <div class="bg-white rounded-3xl shadow-3xl max-w-4xl w-full max-h-[95vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-12 text-center relative">
                    <h2 class="text-4xl font-black">Complete Your Subscription</h2>
                    <span class="close-modal absolute top-6 right-8 text-5xl cursor-pointer hover:opacity-70">&times;</span>
                </div>

                <div class="p-10">
                    <div class="flex justify-center gap-32 mb-12">
                        <div class="step active text-center">
                            <div class="w-20 h-20 bg-purple-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-3">1</div>
                            <p class="font-medium">Personal Info</p>
                        </div>
                        <div class="step" id="step2-label">
                            <div class="w-20 h-20 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-3">2</div>
                            <p class="font-medium">Payment</p>
                        </div>
                    </div>

                    <?php if ($error_message): ?>
                        <div class="bg-red-100 border-l-6 border-red-500 text-red-700 p-6 rounded-xl mb-8"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <!-- STEP 1 -->
                    <form method="POST" id="step1">
                        <input type="hidden" name="action" value="subscribe">
                        <input type="hidden" name="package" id="selectedPlan" value="<?php echo $sub_data['package'] ?? 'medium'; ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                            <div><label class="block font-semibold mb-3">Full Name *</label><div class="relative"><i class="fas fa-user absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="text" name="name" value="<?php echo htmlspecialchars($sub_data['name'] ?? ''); ?>" required class="w-full pl-14 pr-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none"></div></div>
                            <div><label class="block font-semibold mb-3">Email Address *</label><div class="relative"><i class="fas fa-envelope absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="email" name="email" value="<?php echo htmlspecialchars($sub_data['email'] ?? ''); ?>" required class="w-full pl-14 pr-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none"></div></div>
                            <div><label class="block font-semibold mb-3">Phone Number *</label><div class="relative"><i class="fas fa-phone absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="tel" name="phone" value="<?php echo htmlspecialchars($sub_data['phone'] ?? ''); ?>" required class="w-full pl-14 pr-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none"></div></div>
                            <div><label class="block font-semibold mb-3">Country *</label><div class="relative"><i class="fas fa-globe absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i><select name="country" required class="w-full pl-14 pr-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none appearance-none">
                                <option value="">Select Country</option>
                                <option value="PH" <?php echo ($sub_data['country']??'')=='PH'?'selected':''; ?>>Philippines</option>
                                <option value="US">United States</option>
                                <option value="CA">Canada</option>
                            </select></div></div>
                        </div>
                        <div class="text-center"><button type="submit" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white font-bold py-5 px-16 rounded-2xl text-xl hover:shadow-2xl transform hover:scale-105 transition-all">Continue to Payment</button></div>
                    </form>

                    <!-- STEP 2 -->
                    <form method="POST" id="step2" style="display:none;">
                        <input type="hidden" name="action" value="payment">
                        <div class="flex justify-center gap-10 mb-12 flex-wrap">
                            <div class="payment-option selected" onclick="selectPayment('card')"><input type="radio" name="payment_method" value="card" checked hidden><div class="text-6xl mb-4"><i class="fas fa-credit-card"></i></div><p class="font-bold text-lg">Credit Card</p></div>
                            <div class="payment-option" onclick="selectPayment('paypal')"><input type="radio" name="payment_method" value="paypal" hidden><div class="text-6xl mb-4 text-blue-600"><i class="fab fa-paypal"></i></div><p class="font-bold text-lg">PayPal</p></div>
                            <div class="payment-option" onclick="selectPayment('bank')"><input type="radio" name="payment_method" value="bank" hidden><div class="text-6xl mb-4"><i class="fas fa-university"></i></div><p class="font-bold text-lg">Bank Transfer</p></div>
                        </div>

                        <div id="cardDetails" class="payment-details active">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div><label class="block font-semibold mb-3">Card Number *</label><input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" class="w-full px-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none"></div>
                                <div><label class="block font-semibold mb-3">Name on Card *</label><input type="text" name="card_name" class="w-full px-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none"></div>
                                <div><label class="block font-semibold mb-3">Expiry Date *</label><input type="text" id="expiry" name="expiry" placeholder="MM/YY" maxlength="5" class="w-full px-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none"></div>
                                <div><label class="block font-semibold mb-3">CVV *</label><input type="text" name="cvv" maxlength="4" placeholder="123" class="w-full px-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none"></div>
                            </div>
                        </div>

                        <div id="paypalDetails" class="payment-details"><label class="block font-semibold mb-3">PayPal Email *</label><input type="email" name="paypal_email" class="w-full px-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none"></div>

                        <div id="bankDetails" class="payment-details">
                            <label class="block font-semibold mb-3">Account Name *</label><input type="text" name="account_name" class="w-full px-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none mb-6">
                            <label class="block font-semibold mb-3">Account Number *</label><input type="text" name="account_number" class="w-full px-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none mb-6">
                            <label class="block font-semibold mb-3">Bank Name *</label><input type="text" name="bank_name" class="w-full px-6 py-5 border-2 border-gray-200 rounded-2xl focus:border-purple-500 outline-none">
                        </div>

                        <div class="my-12"><label class="flex items-center"><input type="checkbox" name="terms" required class="mr-4 w-6 h-6 text-purple-600 rounded"><span>I agree to the <a href="#" class="text-purple-600 font-bold underline">terms and conditions</a></span></label></div>

                        <div class="flex justify-between items-center">
                            <button type="button" onclick="showStep1()" class="bg-gray-300 text-gray-700 font-bold py-4 px-10 rounded-2xl hover:bg-gray-400 transition">Back</button>
                            <button type="submit" class="bg-gradient-to-r from-purple-600 to-pink-600 text-white font-bold py-5 px-16 rounded-2xl text-xl hover:shadow-2xl transform hover:scale-105 transition-all">Complete Subscription</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Modal & Payment Logic
document.querySelectorAll('[data-plan]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('selectedPlan').value = btn.dataset.plan;
        document.getElementById('subscriptionModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });
});
document.querySelector('.close-modal').onclick = () => {
    document.getElementById('subscriptionModal').style.display = 'none';
    document.body.style.overflow = 'auto';
};
window.onclick = e => { if (e.target === document.getElementById('subscriptionModal')) { document.getElementById('subscriptionModal').style.display = 'none'; document.body.style.overflow = 'auto'; } };

// Card formatting
document.getElementById('card_number')?.addEventListener('input', e => {
    let v = e.target.value.replace(/\s/g,'').replace(/[^0-9]/gi,'');
    let formatted = ''; for(let i=0; i<v.length; i+=4) formatted += v.substr(i,4)+' ';
    e.target.value = formatted.trim().substring(0,19);
});
document.getElementById('expiry')?.addEventListener('input', e => {
    let v = e.target.value.replace(/\D/g,'');
    if(v.length >= 2) e.target.value = v.substring(0,2) + '/' + v.substring(2,4);
});

// Payment method selection
function selectPayment(method) {
    document.querySelectorAll('.payment-option').forEach(el => el.classList.remove('selected'));
    event.target.closest('.payment-option').classList.add('selected');
    document.querySelector(`input[value="${method}"]`).checked = true;
    document.querySelectorAll('.72-payment-details').forEach(el => el.classList.remove('active'));
    document.getElementById(method + 'Details').classList.add('active');
}

function showStep1() {
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    document.getElementById('step2-label').querySelector('.w-20').classList.remove('bg-purple-600','text-white');
    document.getElementById('step2-label').querySelector('.w-20').classList.add('bg-gray-300','text-gray-600');
}

// Step 2 auto-open
<?php if ($step === 2): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    document.getElementById('step2-label').querySelector('.w-20').classList.add('bg-purple-600','text-white');
    document.getElementById('step2-label').querySelector('.w-20').classList.remove('bg-gray-300','text-gray-600');
    document.getElementById('subscriptionModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
});
<?php endif; ?>

// Stagger animations
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.stagger').forEach((el, i) => {
        setTimeout(() => el.classList.add('show'), i * 100);
    });
});
</script>

</body>
</html>