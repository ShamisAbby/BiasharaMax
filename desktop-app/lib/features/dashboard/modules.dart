import 'package:flutter/material.dart';

/// The desktop app's module list, mirroring the web vendor sidebar.
///
/// [available] is the honest bit. Only POS is built here today; the rest
/// exist on the web and are being brought across module by module. They
/// are listed rather than hidden so the shape of the product is visible,
/// and each one says plainly that it is not ready — a menu entry that
/// opens an empty screen teaches people the app is broken, which is a
/// more expensive lesson than "not here yet".
class DesktopModule {
  const DesktopModule({
    required this.key,
    required this.label,
    required this.icon,
    this.available = false,
    this.route,
    this.summary,
  });

  final String key;
  final String label;
  final IconData icon;
  final bool available;
  final String? route;

  /// What the module does, shown on its placeholder. Someone evaluating
  /// the desktop app should be able to see what is coming without
  /// opening the web dashboard alongside it.
  final String? summary;
}

const desktopModules = <DesktopModule>[
  DesktopModule(
    key: 'pos',
    label: 'Point of sale',
    icon: Icons.point_of_sale_outlined,
    available: true,
    route: '/pos',
    summary: 'Ring up sales, take payments and print receipts. Works offline.',
  ),
  DesktopModule(
    key: 'inventory',
    label: 'Inventory',
    icon: Icons.inventory_2_outlined,
    summary: 'Products, categories, stock levels, transfers and stock counts.',
  ),
  DesktopModule(
    key: 'sales',
    label: 'Sales',
    icon: Icons.receipt_long_outlined,
    summary: 'Past orders, returns and daily sales summaries.',
  ),
  DesktopModule(
    key: 'purchasing',
    label: 'Purchasing',
    icon: Icons.local_shipping_outlined,
    summary: 'Suppliers, purchase orders and goods received notes.',
  ),
  DesktopModule(
    key: 'crm',
    label: 'Customers',
    icon: Icons.people_outline,
    summary: 'Customer records, groups, loyalty and feedback.',
  ),
  DesktopModule(
    key: 'finance',
    label: 'Finance',
    icon: Icons.account_balance_outlined,
    summary: 'Journal, chart of accounts, bank reconciliation, income and expenses.',
  ),
  DesktopModule(
    key: 'employees',
    label: 'Employees',
    icon: Icons.badge_outlined,
    summary: 'Staff records, attendance, leave and payroll.',
  ),
  DesktopModule(
    key: 'reports',
    label: 'Reports',
    icon: Icons.insights_outlined,
    summary: 'Sales, stock and financial reporting.',
  ),
  DesktopModule(
    key: 'settings',
    label: 'Settings',
    icon: Icons.settings_outlined,
    summary: 'Business profile, branches, warehouses, roles and subscription.',
  ),
];
