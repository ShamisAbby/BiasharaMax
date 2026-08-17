import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../cart/cart_controller.dart';
import '../receipt/receipt_dialog.dart';

/// Cash-only for this pass — card/mobile-money split payments are a
/// straightforward extension of the same `payments` array
/// SaleService::create() already accepts (see its docblock), just not
/// built out yet since there's no payment terminal integration in this
/// codebase to test it against.
class PaymentSheet extends ConsumerStatefulWidget {
  const PaymentSheet({super.key});

  @override
  ConsumerState<PaymentSheet> createState() => _PaymentSheetState();
}

class _PaymentSheetState extends ConsumerState<PaymentSheet> {
  late final TextEditingController _amountController;
  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final total = ref.read(cartTotalsProvider).totalAmount;
    _amountController = TextEditingController(text: total.toStringAsFixed(0));
  }

  Future<void> _confirm() async {
    final totals = ref.read(cartTotalsProvider);
    final items = ref.read(cartControllerProvider);
    final tendered = double.tryParse(_amountController.text) ?? 0;

    if (tendered < totals.totalAmount) {
      setState(() => _error = 'Amount tendered is less than the total due.');

      return;
    }

    final storage = ref.read(secureStorageProvider);
    final branchId = await storage.getActiveBranchId();
    final warehouseId = await storage.getActiveWarehouseId();

    // Two awaits above, and `setState` on a disposed State throws rather
    // than no-opping. The analyzer doesn't catch this one — its async-gap
    // lint only tracks BuildContext — so it has to be checked by hand.
    if (!mounted) return;

    if (branchId == null || warehouseId == null) {
      setState(() => _error = 'This till has no branch/warehouse set — sign out and select one again.');

      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final payload = {
        'branch_id': branchId,
        'warehouse_id': warehouseId,
        'items': items.map((item) => item.toSalePayload()).toList(),
        'discount_amount': totals.discountAmount,
        'payments': [
          {'amount': totals.totalAmount, 'payment_method': 'cash'},
        ],
      };

      await ref.read(saleRepositoryProvider).queueSale(payload);
      ref.read(cartControllerProvider.notifier).clear();
      ref.read(syncManagerProvider).syncNow(); // best-effort, immediate attempt; outbox covers it either way

      // The sale is written by this point, so the cashier can legitimately
      // have dismissed the sheet while it was in flight. Nothing below is
      // safe on a defunct element, and the sale is not lost by skipping it
      // — only the receipt is, which is reprintable.
      if (!mounted) return;

      // `Navigator.of(context)` and `showDialog(context: context)` both
      // resolve against *this* widget's element, which dies the moment the
      // sheet pops. So the navigator is taken first and the receipt is
      // shown through the navigator's own context, which outlives the
      // route. No `await` between these lines, deliberately: another gap
      // here would invalidate the context all over again.
      final navigator = Navigator.of(context);
      final rootContext = navigator.context;

      navigator.pop();

      // Two separate checks because they are two separate elements. The
      // `mounted` above is this sheet's; it says nothing about whether the
      // Navigator is still in the tree — which it won't be if the whole
      // POS route was torn down while the sale was being written. The
      // analyzer is strict about this distinction and it is right to be:
      // one liveness check standing in for another is how you get a crash
      // that only reproduces on a session timeout mid-checkout.
      if (rootContext.mounted) {
        showDialog(
          context: rootContext,
          builder: (context) => ReceiptDialog(items: items, totals: totals, amountTendered: tendered),
        );
      }
    } catch (e) {
      // Previously a bare try/finally: if the sale failed to reach the
      // outbox the cashier saw the button stop spinning and nothing else,
      // with no sale recorded and no idea anything had gone wrong.
      if (mounted) {
        setState(() => _error = 'Could not record this sale: $e');
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final totals = ref.watch(cartTotalsProvider);
    final tendered = double.tryParse(_amountController.text) ?? 0;
    final change = tendered - totals.totalAmount;

    return Padding(
      padding: EdgeInsets.only(
        left: 24,
        right: 24,
        top: 24,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Total due: TZS ${totals.totalAmount.toStringAsFixed(0)}',
              style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 16),
          TextField(
            controller: _amountController,
            decoration: const InputDecoration(labelText: 'Cash tendered', border: OutlineInputBorder()),
            keyboardType: TextInputType.number,
            onChanged: (_) => setState(() {}),
            autofocus: true,
          ),
          const SizedBox(height: 8),
          Text(
            change >= 0 ? 'Change due: TZS ${change.toStringAsFixed(0)}' : 'Amount is short',
            style: TextStyle(color: change >= 0 ? null : Theme.of(context).colorScheme.error),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          ],
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _submitting ? null : _confirm,
            child: _submitting
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Confirm sale'),
          ),
        ],
      ),
    );
  }
}
