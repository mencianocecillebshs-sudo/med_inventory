<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Purchases (selling to customers) is a Pharmacist-only function — only pharmacists
// process sales, so this page no longer lives in the Admin portal. Send admins to
// the Sales page instead, where they can view/report on everything pharmacists ring up.
header("Location: sales.php");
exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchases - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; overflow: hidden; }
        .main-content { margin-left: 250px; padding: 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 1rem; } }
        .card { border-radius: 15px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); background: #fff; border: none; }
        .table-container { max-height: calc(100vh - 320px); overflow-y: auto; overflow-x: auto; }
        .table-container::-webkit-scrollbar { width: 10px; height: 10px; }
        .table-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .table { border-radius: 12px; overflow: hidden; margin-bottom: 0; }
        .table th { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; font-weight: 600; padding: 1rem; border: none; position: sticky; top: 0; z-index: 10; }
        .table td { vertical-align: middle; color: #2d3748; padding: 1rem; border-color: #e2e8f0; }
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background: #f7fafc; transform: translateX(2px); }
        .status-badge { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-pending   { background: linear-gradient(135deg, #ffd93d 0%, #fcb900 100%); color: #5a4200; }
        .status-filled    { background: linear-gradient(135deg, #34d399 0%, #10b981 100%); color: #064e3b; }
        .status-cancelled { background: linear-gradient(135deg, #f87171 0%, #ef4444 100%); color: #7f1d1d; }
        .action-btn { padding: 0.5rem 1rem; font-size: 0.9rem; border-radius: 8px; transition: all 0.3s ease; margin: 0 0.2rem; font-weight: 500; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; }
        .btn-success:hover { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .btn-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border: none; color: #78350f; }
        .btn-warning:hover { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none; }
        .btn-danger:hover { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        .btn-info { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); border: none; color: #fff !important; }
        .btn-info:hover { background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%); }
        .btn-download { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); border: none; color: #fff !important; }
        .btn-download:hover { background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 100%); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(124,58,237,0.35); }
        .modal-content { border-radius: 25px; border: none; box-shadow: 0 25px 80px rgba(0,0,0,0.4); overflow: hidden; }
        .modal-dialog { max-width: 900px; max-height: 95vh; display: flex; align-items: center; }
        .modal-dialog-scrollable .modal-body { max-height: calc(95vh - 200px); overflow-y: auto; }
        .modal-header.bg-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); box-shadow: 0 4px 20px rgba(27,94,63,0.3); }
        .modal-header.bg-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 4px 20px rgba(16,185,129,0.3); }
        .modal-header.bg-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); box-shadow: 0 4px 20px rgba(251,191,36,0.3); }
        .modal-header.bg-receipt { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); box-shadow: 0 4px 20px rgba(124,58,237,0.3); }
        .modal-header.bg-fill   { background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 4px 20px rgba(16,185,129,0.3); }
        .modal-header.bg-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); box-shadow: 0 4px 20px rgba(239,68,68,0.3); }
        .modal-title { font-size: 1.75rem; font-weight: 700; letter-spacing: -0.5px; text-shadow: 0 2px 4px rgba(0,0,0,0.1); position: relative; z-index: 1; }
        .modal-title i { font-size: 2rem; vertical-align: middle; margin-right: 0.75rem; }
        .form-control, .form-select { border-radius: 12px; border: 2px solid #e2e8f0; transition: all 0.3s ease; padding: 0.85rem 1.25rem; font-size: 1rem; background: white; }
        .form-control:focus, .form-select:focus { border-color: #1b5e3f; box-shadow: 0 0 0 0.3rem rgba(27,94,63,0.12), 0 4px 12px rgba(27,94,63,0.08); transform: translateY(-2px); background: #ffffff; }
        .form-label { font-weight: 700; color: #1e293b; margin-bottom: 0.75rem; font-size: 1rem; display: flex; align-items: center; }
        .form-label i { margin-right: 0.5rem; color: #1b5e3f; font-size: 1.1rem; }
        .form-section { background: white; border-radius: 15px; padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .form-section:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .form-section-title { font-size: 1.1rem; font-weight: 700; color: #1b5e3f; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 2px solid #e2e8f0; display: flex; align-items: center; }
        .form-section-title i { margin-right: 0.5rem; font-size: 1.3rem; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem; }
        .form-group-icon { position: relative; }
        .form-group-icon i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1.1rem; pointer-events: none; }
        .form-group-icon .form-control,
        .form-group-icon .form-select { padding-left: 3rem; }
        .modal-footer { padding: 1.75rem 2.5rem; background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border-top: 2px solid #e2e8f0; border-radius: 0 0 25px 25px; }
        .invalid-feedback { font-size: 0.85rem; color: #dc2626; font-weight: 500; }
        #toggle-sidebar-mobile { position: fixed; top: 1rem; left: 1rem; z-index: 1100; background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; border-radius: 50%; width: 45px; height: 45px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .input-group-text { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 2px solid #e2e8f0; font-weight: 600; color: #1b5e3f; }
        h2 { color: #1e293b; font-weight: 700; }
        select option { color: #000 !important; background-color: #fff !important; }
        .card-body { padding: 0; }
        .page-header { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem; box-shadow: 0 8px 24px rgba(27,94,63,0.2); }
        .page-header h2 { color: white; margin: 0; }
        .loading-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); display: none; align-items: center; justify-content: center; z-index: 100; border-radius: 15px; }
        .loading-overlay.show { display: flex; }
        .table-wrapper { position: relative; }
        .notes-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .notes-table th, .notes-table td { border: 1px solid #e2e8f0; padding: 0.75rem; text-align: left; }
        .notes-table th { background: #f8fafc; font-weight: 600; color: #1b5e3f; }
        .notes-table textarea { width: 100%; border: none; resize: vertical; min-height: 60px; }
        .add-note-btn { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; color: white; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; }
        .add-note-btn:hover { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .info-badge { background: #e6fffa; border: 1px solid #0f766e; color: #065f46; padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; }
        .info-badge i { margin-right: 0.5rem; font-size: 1.1rem; }
        .required-indicator { color: #dc2626; font-weight: bold; }

        /* ── Medicine items table ────────────────────────────────── */
        .med-items-table { width: 100%; border-collapse: collapse; }
        .med-items-table th,
        .med-items-table td { border: 1px solid #e2e8f0; padding: 0.75rem 0.9rem; vertical-align: middle; }
        .med-items-table thead th { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; font-weight: 600; font-size: 0.9rem; letter-spacing: 0.3px; }
        .med-items-table tbody tr:hover { background: #f0fdf4; }
        .med-items-table .form-control,
        .med-items-table .form-select { border-radius: 8px; padding: 0.55rem 0.9rem; font-size: 0.95rem; }
        .med-items-table .form-control:focus { transform: none; }
        .med-picker-cell  { min-width: 380px; }
        .med-qty-cell     { width: 120px; }
        .med-linetotal-cell { width: 170px; }
        .med-del-cell     { width: 62px; }

        /* ── Medicine Combobox (mirrors type-combobox from medicines page) ── */
        .med-combobox {
            position: relative;
        }
        .med-combobox .med-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .med-combobox .med-display-input {
            padding-right: 2.8rem !important;
            border-radius: 10px !important;
            font-size: 0.95rem !important;
            border: 2px solid #e2e8f0 !important;
            transition: border-color 0.2s, box-shadow 0.2s;
            height: auto !important;
            padding-top: 0.7rem !important;
            padding-bottom: 0.7rem !important;
            padding-left: 0.9rem !important;
        }
        .med-combobox .med-display-input:focus {
            border-color: #1b5e3f !important;
            box-shadow: 0 0 0 0.25rem rgba(27,94,63,0.13) !important;
            outline: none;
            transform: none !important;
        }
        .med-combobox .med-display-input.is-invalid {
            border-color: #dc2626 !important;
        }
        .med-combobox .med-chevron {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
            transition: transform 0.2s ease;
            font-size: 1rem;
        }
        .med-combobox.open .med-chevron {
            transform: translateY(-50%) rotate(180deg);
        }
        .med-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #fff;
            border: 2px solid #1b5e3f;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(27,94,63,0.15);
            z-index: 1060;
            overflow: hidden;
            max-height: 240px;
            flex-direction: column;
        }
        .med-combobox.open .med-dropdown {
            display: flex;
        }

        .med-dropdown-list {
            overflow-y: auto;
            flex: 1;
        }
        .med-dropdown-list::-webkit-scrollbar { width: 6px; }
        .med-dropdown-list::-webkit-scrollbar-thumb { background: #1b5e3f; border-radius: 6px; }
        .med-dd-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.65rem 1rem;
            cursor: pointer;
            font-size: 0.9rem;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }
        .med-dd-item:last-child { border-bottom: none; }
        .med-dd-item:hover,
        .med-dd-item.active { background: #f0fdf4; color: #1b5e3f; }
        .med-dd-name { font-weight: 500; }
        .med-dd-price {
            font-size: 0.82rem;
            font-weight: 700;
            color: #059669;
            white-space: nowrap;
            margin-left: 0.5rem;
            background: #dcfce7;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
        }
        .med-dd-empty {
            padding: 0.85rem 1rem;
            color: #94a3b8;
            font-size: 0.9rem;
            text-align: center;
        }
        .med-selected-label {
            font-size: 0.82rem;
            color: #059669;
            font-weight: 700;
            margin-top: 0.3rem;
            padding-left: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .med-selected-label::before { content: "✓"; font-size: 0.75rem; }
        .med-invalid { font-size: 0.82rem; color: #dc2626; font-weight: 500; display: none; margin-top: 0.2rem; }

        /* ── Line total & grand total ──────────────────────────── */
        .med-row-line-total-display {
            font-size: 1.05rem; font-weight: 700; color: #059669;
            display: block; text-align: right; letter-spacing: 0.3px;
            padding: 0.35rem 0.6rem; background: #f0fdf4;
            border-radius: 8px; border: 1.5px solid #bbf7d0;
            min-width: 120px;
        }
        .med-row-line-total-display.line-total-zero { color: #94a3b8; background: #f8fafc; border-color: #e2e8f0; }
        .med-grand-total-wrap {
            display: flex; align-items: center; justify-content: flex-end;
            gap: 0.75rem; padding: 1rem 0.75rem 0.5rem;
        }
        .med-grand-total-label { font-size: 0.9rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        .med-grand-total-value {
            font-size: 1.6rem; font-weight: 800; color: #065f46;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 2px solid #34d399; border-radius: 12px;
            padding: 0.35rem 1rem; min-width: 170px; text-align: right;
            box-shadow: 0 2px 8px rgba(16,185,129,0.15); letter-spacing: 0.5px;
        }

        /* ── Fill Modal pills ──────────────────────────────────── */
        .fill-detail-pill { display: flex; align-items: center; gap: 0.75rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 0.75rem; }
        .fill-detail-pill i { color: #059669; font-size: 1.15rem; flex-shrink: 0; }
        .fill-detail-pill .pill-label { font-size: 0.78rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1; }
        .fill-detail-pill .pill-value { font-size: 0.95rem; color: #0f172a; font-weight: 700; line-height: 1.2; }

        /* ── Receipt ──────────────────────────────────────────── */
        #receiptModal .modal-dialog { max-width: 540px; }
        #receiptModal .modal-footer { gap: 0.5rem; flex-wrap: wrap; }
        #receipt-printable { font-family: 'Courier New', Courier, monospace; background: #fff; color: #111; padding: 1.75rem; border-radius: 12px; border: 2px dashed #cbd5e1; }
        #receipt-printable .rx-header { text-align: center; margin-bottom: 1rem; border-bottom: 2px solid #1b5e3f; padding-bottom: 0.75rem; }
        #receipt-printable .rx-header h4 { font-size: 1.2rem; font-weight: 800; color: #1b5e3f; margin: 0; letter-spacing: 1px; }
        #receipt-printable .rx-header p  { font-size: 0.78rem; color: #64748b; margin: 2px 0 0; }
        #receipt-printable .rx-number { text-align: center; font-size: 1rem; font-weight: 700; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 0.4rem 0.8rem; margin: 0.75rem 0; color: #065f46; letter-spacing: 1px; }
        #receipt-printable .rx-row { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px dotted #e2e8f0; font-size: 0.88rem; gap: 1rem; }
        #receipt-printable .rx-row .label { color: #475569; font-weight: 600; white-space: nowrap; }
        #receipt-printable .rx-row .value { color: #111; font-weight: 500; text-align: right; word-break: break-word; }
        #receipt-printable .rx-total { display: flex; justify-content: space-between; padding: 0.65rem 0; margin-top: 0.5rem; font-size: 1.05rem; font-weight: 800; border-top: 2px solid #1b5e3f; color: #1b5e3f; }
        #receipt-printable .rx-notes { background: #f8fafc; border-left: 3px solid #7c3aed; border-radius: 4px; padding: 0.75rem; margin-top: 0.75rem; font-size: 0.82rem; color: #475569; white-space: pre-wrap; line-height: 1.6; }
        #receipt-printable .rx-footer { text-align: center; margin-top: 1rem; font-size: 0.75rem; color: #94a3b8; border-top: 1px dashed #e2e8f0; padding-top: 0.75rem; line-height: 1.7; }
        .rx-status-filled    { color: #065f46; background: #dcfce7; padding: 2px 8px; border-radius: 4px; display: inline-block; }
        .rx-status-pending   { color: #854d0e; background: #fef9c3; padding: 2px 8px; border-radius: 4px; display: inline-block; }
        .rx-status-cancelled { color: #7f1d1d; background: #fee2e2; padding: 2px 8px; border-radius: 4px; display: inline-block; }
        #btn-download-pdf .spinner-border { width: 0.85rem; height: 0.85rem; border-width: 0.15em; }

        @media print {
            body * { visibility: hidden !important; }
            #receipt-printable, #receipt-printable * { visibility: visible !important; }
            #receipt-printable { position: fixed !important; inset: 0; width: 80mm; margin: auto; padding: 8mm; border: none; border-radius: 0; font-size: 10.5pt; box-shadow: none; }
        }

        /* ── Dark mode ──────────────────────────────────────────── */
        body.dark-mode .modal-body,
        body.dark-mode .modal-footer,
        body.dark-mode .form-section,
        body.dark-mode .notes-table,
        body.dark-mode .notes-table th,
        body.dark-mode .notes-table td { background: #111827 !important; color: #e2e8f0 !important; border-color: #334155 !important; }
        body.dark-mode .modal .form-label,
        body.dark-mode .modal .form-section-title,
        body.dark-mode .modal small,
        body.dark-mode .modal p,
        body.dark-mode .modal span { color: #e2e8f0 !important; }
        body.dark-mode .notes-table textarea { background: #0f172a !important; color: #e2e8f0 !important; }
        body.dark-mode .med-items-table th,
        body.dark-mode .med-items-table td { border-color: #334155 !important; }
        body.dark-mode .med-items-table tbody tr:hover { background: #14532d !important; }
        body.dark-mode .med-dropdown { background: #1e293b; border-color: #4ade80; }

        body.dark-mode .med-dd-item { color: #f1f5f9; border-bottom-color: #334155; }
        body.dark-mode .med-dd-item:hover,
        body.dark-mode .med-dd-item.active { background: #14532d; color: #4ade80; }
        body.dark-mode .med-dd-price { background: #14532d; color: #4ade80; }
        body.dark-mode .med-display-input { background: #0f172a !important; color: #f1f5f9 !important; border-color: #334155 !important; }
        body.dark-mode .med-chevron { color: #94a3b8; }
        body.dark-mode .med-selected-label { color: #4ade80; }
        body.dark-mode .med-row-line-total-display { background: #14532d; border-color: #166534; color: #4ade80; }
        body.dark-mode .med-row-line-total-display.line-total-zero { background: #1e293b; border-color: #334155; color: #64748b; }
        body.dark-mode .med-grand-total-value { background: linear-gradient(135deg, #14532d 0%, #166534 100%); border-color: #4ade80; color: #4ade80; }
        body.dark-mode .med-grand-total-label { color: #94a3b8; }
        body.dark-mode #receipt-printable { background: #1e293b !important; color: #f1f5f9 !important; border-color: #334155 !important; }
        body.dark-mode #receipt-printable .rx-header { border-bottom-color: #4ade80 !important; }
        body.dark-mode #receipt-printable .rx-header h4 { color: #4ade80 !important; }
        body.dark-mode #receipt-printable .rx-header p  { color: #94a3b8 !important; }
        body.dark-mode #receipt-printable .rx-number { background: #14532d !important; border-color: #166534 !important; color: #4ade80 !important; }
        body.dark-mode #receipt-printable .rx-row { border-bottom-color: #334155 !important; }
        body.dark-mode #receipt-printable .rx-row .label { color: #94a3b8 !important; }
        body.dark-mode #receipt-printable .rx-row .value { color: #f1f5f9 !important; }
        body.dark-mode #receipt-printable .rx-total { color: #4ade80 !important; border-top-color: #4ade80 !important; }
        body.dark-mode #receipt-printable .rx-notes { background: #0f172a !important; color: #94a3b8 !important; border-left-color: #7c3aed !important; }
        body.dark-mode #receipt-printable .rx-footer { color: #64748b !important; border-top-color: #334155 !important; }
        body.dark-mode .rx-status-filled    { background: #14532d !important; color: #4ade80 !important; }
        body.dark-mode .rx-status-pending   { background: #422006 !important; color: #fbbf24 !important; }
        body.dark-mode .rx-status-cancelled { background: #450a0a !important; color: #fca5a5 !important; }
        body.dark-mode .fill-detail-pill { background: #14532d !important; border-color: #166534 !important; }
        body.dark-mode .fill-detail-pill .pill-label { color: #86efac !important; }
        body.dark-mode .fill-detail-pill .pill-value { color: #f0fdf4 !important; }
        body.dark-mode .fill-detail-pill i { color: #4ade80 !important; }
        body.dark-mode .info-badge { background: #14532d !important; border-color: #166534 !important; color: #86efac !important; }
        body.dark-mode .table td { color: #e2e8f0 !important; }
        body.dark-mode .card { background: #1e293b !important; }
        body.dark-mode .loading-overlay { background: rgba(15,23,42,0.8) !important; }
        body.dark-mode .modal-footer { background: #0f172a !important; border-top-color: #334155 !important; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>

    <div class="main-content admin-table-page">
        <div class="page-header">
            <h2><i class="bi bi-file-medical me-2"></i> Purchase Management</h2>
        </div>
        <div class="d-flex mb-4 align-items-center gap-2 flex-wrap">
            <button class="btn btn-success action-btn" data-bs-toggle="modal" data-bs-target="#newPurchaseModal">
                <i class="bi bi-plus-circle me-1"></i> New Purchase
            </button>
            <input type="text" class="form-control" id="search" placeholder="Search purchases..." style="width:250px;min-width:200px;">
            <select class="form-select" id="medicine-filter" style="width:200px;min-width:200px;">
                <option value="">All Medicines</option>
            </select>
            <select class="form-select" id="status-filter" style="width:200px;min-width:200px;">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="filled">Filled</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div class="card admin-table-card">
            <div class="card-body p-0">
                <div class="table-wrapper">
                    <div class="loading-overlay" id="table-loading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div class="table-container admin-table-scroll">
                        <table class="table table-hover mb-0" data-admin-no-pagination="true">
                            <thead>
                                <tr>
                                    <th>Purchase #</th>
                                    <th>Medicine</th>
                                    <th>Quantity</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Total Cost</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="purchase-table">
                                <tr id="no-data-row" class="d-none">
                                    <td colspan="8" class="text-center text-muted py-5">No purchases found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small id="purchase-page-info" class="text-muted"></small>
                    <nav id="purchase-pagination" aria-label="Purchase pages"></nav>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         FILL CONFIRMATION MODAL
    ================================================================ -->
    <div class="modal fade" id="fillPurchaseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
            <div class="modal-content">
                <div class="modal-header bg-fill text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle-fill"></i> Fill Purchase
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3 fw-semibold" style="font-size:1rem;">
                        You are about to mark this purchase as <strong>Filled</strong>.
                        Stock will be deducted automatically.
                    </p>
                    <div id="fill-modal-details"></div>
                    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0 mt-3" style="border-radius:10px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>This action <strong>cannot be undone</strong> once confirmed.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-success action-btn" id="confirm-fill-btn">
                        <i class="bi bi-check-lg me-1"></i> Confirm Fill
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         RECEIPT MODAL
    ================================================================ -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-receipt text-white">
                    <h5 class="modal-title" id="receiptModalLabel">
                        <i class="bi bi-receipt"></i> Purchase Receipt
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div id="receipt-printable">
                        <div class="rx-header">
                            <h4><i class="bi bi-capsule-pill"></i>&nbsp; PHARMACY RECEIPT</h4>
                            <p>Medicine Inventory System &mdash; Official Purchase Document</p>
                            <p id="rx-print-date" style="font-size:0.75rem;color:#94a3b8;"></p>
                        </div>
                        <div class="rx-number" id="rx-number"></div>
                        <div class="rx-row"><span class="label">Medicine</span>          <span class="value" id="rx-medicine"></span></div>
                        <div class="rx-row"><span class="label">Quantity</span>          <span class="value" id="rx-quantity"></span></div>
                        <div class="rx-row"><span class="label">Purchase Date</span> <span class="value" id="rx-date"></span></div>
                        <div class="rx-row"><span class="label">Status</span>            <span class="value" id="rx-status"></span></div>
                        <div class="rx-row"><span class="label">Dispensed By</span>      <span class="value" id="rx-pharmacist"></span></div>
                        <div class="rx-total">
                            <span>TOTAL COST</span>
                            <span id="rx-total-cost"></span>
                        </div>
                        <div class="rx-notes d-none" id="rx-notes-wrap">
                            <strong>Notes &amp; Instructions:</strong><br><span id="rx-notes"></span>
                        </div>
                        <div class="rx-footer">
                            <p>Thank you for choosing our pharmacy.</p>
                            <p>Please keep this receipt for your records.</p>
                            <p id="rx-footer-number" style="font-weight:700;"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Close
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-download action-btn" id="btn-download-pdf">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                        </button>
                        <button type="button" class="btn btn-info action-btn" id="btn-print-receipt">
                            <i class="bi bi-printer me-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         DELETE MODAL
    ================================================================ -->
    <div class="modal fade" id="deletePurchaseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash"></i> Delete Purchase</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to delete this purchase?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger action-btn" id="confirm-delete-purchase-btn">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         EDIT MODAL
    ================================================================ -->
    <div class="modal fade" id="editPurchaseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Purchase</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="info-badge">
                        <i class="bi bi-info-circle-fill"></i>
                        Update purchase details. Changing status to "Filled" will deduct stock automatically.
                    </div>
                    <form id="editPurchaseForm" novalidate>
                        <input type="hidden" id="edit_id">
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-hash"></i> Purchase Identification</div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-upc-scan"></i> Purchase Number</label>
                                <div class="form-group-icon">
                                    <i class="bi bi-shield-check"></i>
                                    <input type="text" class="form-control" id="edit_purchase_number" readonly>
                                </div>
                                <small class="text-muted">Auto-generated and cannot be changed</small>
                            </div>
                        </div>
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-capsule"></i> Medicine Information</div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-bag-check"></i> Medicine <span class="required-indicator">*</span></label>
                                <div class="form-group-icon">
                                    <i class="bi bi-search"></i>
                                    <select class="form-select" id="edit_medicine_id" required><option value="">Select Medicine</option></select>
                                </div>
                                <div class="invalid-feedback">Please select a medicine.</div>
                            </div>
                            <div class="form-row">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-123"></i> Quantity <span class="required-indicator">*</span></label>
                                    <div class="form-group-icon">
                                        <i class="bi bi-box-seam"></i>
                                        <input type="number" class="form-control" id="edit_quantity" min="1" required>
                                    </div>
                                    <div class="invalid-feedback">Quantity must be at least 1.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-calendar-check"></i> Purchase Date</label>
                                    <div class="form-group-icon">
                                        <i class="bi bi-calendar3"></i>
                                        <input type="date" class="form-control" id="edit_purchase_date" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-activity"></i> Status &amp; Processing</div>
                            <div class="form-row">
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-flag"></i> Status <span class="required-indicator">*</span></label>
                                    <div class="form-group-icon">
                                        <i class="bi bi-toggles"></i>
                                        <select class="form-select" id="edit_status" required>
                                            <option value="pending">Pending</option>
                                            <option value="filled">Filled</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="invalid-feedback">Please select a status.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><i class="bi bi-person-check"></i> Filled By</label>
                                    <div class="form-group-icon">
                                        <i class="bi bi-person"></i>
                                        <select class="form-select" id="edit_filled_by_user_id"><option value="">Current User</option></select>
                                    </div>
                                    <small class="text-muted">User who fulfilled this purchase</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-cash-coin"></i> Financial Details</div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-currency-exchange"></i> Total Cost <span class="required-indicator">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" step="0.01" class="form-control" id="edit_total_cost" min="0" required>
                                </div>
                                <div class="invalid-feedback">Total cost must be positive.</div>
                            </div>
                        </div>
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-journal-text"></i> Additional Information</div>
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-card-text"></i> Notes &amp; Instructions</label>
                                <textarea class="form-control" id="edit_notes" rows="4" placeholder="Add any special instructions, dosage information, or additional notes..."></textarea>
                                <small class="text-muted">Optional: Add any relevant information about this purchase</small>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" form="editPurchaseForm" class="btn btn-primary action-btn">
                        <i class="bi bi-save me-1"></i> Update Purchase
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         NEW PURCHASE MODAL  (multi-medicine)
    ================================================================ -->
    <div class="modal fade" id="newPurchaseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable" style="max-width:1060px;">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-file-medical-fill"></i> Create New Purchase</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="info-badge">
                        <i class="bi bi-info-circle-fill"></i>
                        Fill in the purchase details below. You can add multiple medicines to one purchase.
                        Fields marked <span class="required-indicator">*</span> are required.
                    </div>
                    <form id="newPurchaseForm" novalidate>
                        <input type="hidden" id="user_id"       value="<?php echo $_SESSION['user_id']; ?>">
                        <input type="hidden" id="pharmacist_id" value="<?php echo $_SESSION['user_id']; ?>">

                        <!-- ── Purchase date ── -->
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-calendar-check"></i> Purchase Date</div>
                            <div class="mb-0" style="max-width:260px;">
                                <label class="form-label"><i class="bi bi-calendar3"></i> Date <span class="required-indicator">*</span></label>
                                <input type="date" class="form-control" id="new_purchase_date"
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Please select a date.</div>
                            </div>
                        </div>

                        <!-- ── Medicine items table ── -->
                        <div class="form-section">
                            <div class="form-section-title d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-capsule"></i> Medicine Items</span>
                                <button type="button" class="btn btn-success btn-sm" id="add-med-row-btn" style="border-radius:8px;">
                                    <i class="bi bi-plus-circle me-1"></i> Add Medicine
                                </button>
                            </div>
                            <small class="text-muted d-block mb-3">
                                Add one or more medicines. Costs are auto-calculated from the selling price.
                                Each medicine will be saved as a separate purchase record with a unique number.
                            </small>
                            <div style="overflow-x:auto;">
                                <table class="med-items-table">
                                    <thead>
                                        <tr>
                                            <th class="med-picker-cell">
                                                <i class="bi bi-search me-1"></i> Medicine <span class="required-indicator">*</span>
                                            </th>
                                            <th class="med-qty-cell">Qty <span class="required-indicator">*</span></th>
                                            <th class="med-linetotal-cell text-end">Line Total</th>
                                            <th class="med-del-cell text-center"><i class="bi bi-trash"></i></th>
                                        </tr>
                                    </thead>
                                    <tbody id="med-items-tbody">
                                        <!-- Rows injected by JS after medicines load -->
                                    </tbody>
                                </table>
                            </div>
                            <div class="med-grand-total-wrap">
                                <span class="med-grand-total-label"><i class="bi bi-receipt me-1"></i> Grand Total</span>
                                <span class="med-grand-total-value" id="new_grand_total_display">₱0.00</span>
                                <input type="hidden" id="new_grand_total" value="0.00">
                            </div>
                        </div>

                        <!-- ── Notes ── -->
                        <div class="form-section">
                            <div class="form-section-title"><i class="bi bi-journal-text"></i> Notes &amp; Instructions</div>
                            <small class="text-muted d-block mb-2">
                                Optional: These notes will be attached to all medicines in this purchase batch.
                                Click "Add Row" for more entries.
                            </small>
                            <table class="notes-table" id="notes-table">
                                <thead>
                                    <tr>
                                        <th style="width:20%;">Note Type</th>
                                        <th style="width:70%;">Details</th>
                                        <th style="width:10%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="notes-table-body"></tbody>
                            </table>
                            <button type="button" class="btn btn-success add-note-btn mt-2" id="add-note-row-btn">
                                <i class="bi bi-plus-circle me-1"></i> Add Note Row
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" form="newPurchaseForm" class="btn btn-success action-btn">
                        <i class="bi bi-check-circle me-1"></i> Create Purchase
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         REMOVE MEDICINE ROW MODAL
    ================================================================ -->
    <div class="modal fade" id="removeMedRowModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash"></i> Remove Medicine</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-2">Are you sure you want to remove this medicine from the purchase?</p>
                    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0" style="border-radius:10px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>The medicine and its quantity will be removed from this form.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger action-btn" id="confirm-remove-med-row-btn">
                        <i class="bi bi-trash me-1"></i> Remove
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         REMOVE NOTE ROW MODAL
    ================================================================ -->
    <div class="modal fade" id="removeNoteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash"></i> Remove Note</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-2">Are you sure you want to remove this note row?</p>
                    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0" style="border-radius:10px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Any text entered in this row will be lost.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger action-btn" id="confirm-remove-note-btn">
                        <i class="bi bi-trash me-1"></i> Remove
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/purchases.js"></script>
</body>
</html>
