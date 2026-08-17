import 'package:flutter/material.dart';

import '../cart/cart_controller.dart';
import '../cart/cart_item.dart';

/// Shown immediately after `SaleRepository.queueSale()` returns — which
/// happens whether or not the server is reachable right now, since
/// queueing is purely local. The "Queued for sync" note is deliberate:
/// this is not a server-confirmed receipt with a real sale number yet
/// (that only exists once SyncManager successfully pushes it), and
/// pretending otherwise would be misleading on a slow/offline connection.
/// Printing is not implemented — this is an on-screen summary only.
class ReceiptDialog extends StatelessWidget {
  const ReceiptDialog({super.key, required this.items, required this.totals, required this.amountTendered});

  final List<CartItem> items;
  final CartTotals totals;
  final double amountTendered;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Sale complete'),
      content: SizedBox(
        width: 360,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ...items.map((item) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(child: Text('${item.product.name} x${item.quantity.toStringAsFixed(0)}')),
                      Text('TZS ${item.lineTotal.toStringAsFixed(0)}'),
                    ],
                  ),
                )),
            const Divider(),
            _row('Total', totals.totalAmount),
            _row('Tendered', amountTendered),
            _row('Change', amountTendered - totals.totalAmount),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.secondaryContainer,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.cloud_sync, size: 18),
                  SizedBox(width: 8),
                  Text('Queued for sync'),
                ],
              ),
            ),
          ],
        ),
      ),
      actions: [
        FilledButton(onPressed: () => Navigator.of(context).pop(), child: const Text('New sale')),
      ],
    );
  }

  Widget _row(String label, double amount) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [Text(label), Text('TZS ${amount.toStringAsFixed(0)}')],
        ),
      );
}
