import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../checkout/payment_sheet.dart';
import 'cart_controller.dart';

/// The right-hand pane of the POS screen — live cart contents + totals,
/// and the entry point into checkout. Pure presentation over
/// `cartControllerProvider` / `cartTotalsProvider`; all the actual math
/// lives in CartItem/CartTotals so this stays a dumb view.
class CartPanel extends ConsumerWidget {
  const CartPanel({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final items = ref.watch(cartControllerProvider);
    final totals = ref.watch(cartTotalsProvider);
    final cart = ref.read(cartControllerProvider.notifier);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: Text('Current sale', style: Theme.of(context).textTheme.titleMedium),
        ),
        Expanded(
          child: items.isEmpty
              ? const Center(child: Text('Cart is empty'))
              : ListView.builder(
                  itemCount: items.length,
                  itemBuilder: (context, index) {
                    final item = items[index];

                    return ListTile(
                      title: Text(item.product.name),
                      subtitle: Text('TZS ${item.unitPrice.toStringAsFixed(0)} each'),
                      leading: _QuantityStepper(
                        quantity: item.quantity,
                        onChanged: (q) => cart.setQuantity(item.product.id, q),
                      ),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text('TZS ${item.lineTotal.toStringAsFixed(0)}'),
                          IconButton(
                            icon: const Icon(Icons.close, size: 18),
                            onPressed: () => cart.removeProduct(item.product.id),
                          ),
                        ],
                      ),
                    );
                  },
                ),
        ),
        const Divider(height: 1),
        Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _TotalRow('Subtotal', totals.subtotal),
              _TotalRow('Discount', -totals.discountAmount),
              _TotalRow('Tax', totals.taxAmount),
              const Divider(),
              _TotalRow('Total', totals.totalAmount, emphasize: true),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: items.isEmpty
                    ? null
                    : () => showModalBottomSheet(
                          context: context,
                          isScrollControlled: true,
                          builder: (context) => const PaymentSheet(),
                        ),
                child: const Text('Charge'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow(this.label, this.amount, {this.emphasize = false});

  final String label;
  final double amount;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final style = emphasize
        ? Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)
        : Theme.of(context).textTheme.bodyMedium;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: style),
          Text('TZS ${amount.toStringAsFixed(0)}', style: style),
        ],
      ),
    );
  }
}

class _QuantityStepper extends StatelessWidget {
  const _QuantityStepper({required this.quantity, required this.onChanged});

  final double quantity;
  final ValueChanged<double> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          icon: const Icon(Icons.remove_circle_outline, size: 18),
          onPressed: () => onChanged(quantity - 1),
        ),
        Text(quantity.toStringAsFixed(quantity.truncateToDouble() == quantity ? 0 : 2)),
        IconButton(
          icon: const Icon(Icons.add_circle_outline, size: 18),
          onPressed: () => onChanged(quantity + 1),
        ),
      ],
    );
  }
}
