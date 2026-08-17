import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../data/local/database.dart';
import 'cart_item.dart';

class CartTotals {
  const CartTotals({
    required this.subtotal,
    required this.discountAmount,
    required this.taxAmount,
    required this.totalAmount,
  });

  final double subtotal;
  final double discountAmount;
  final double taxAmount;
  final double totalAmount;

  static const zero = CartTotals(subtotal: 0, discountAmount: 0, taxAmount: 0, totalAmount: 0);
}

/// Cart is per-till, in-memory only — it's cleared on checkout (the sale
/// itself is what gets persisted, via SaleRepository's outbox) and is
/// deliberately not saved to the local database. If the app is closed
/// mid-sale the cart is lost; making an in-progress cart itself durable
/// is a reasonable future improvement but wasn't necessary to prove the
/// sync pattern this pass is centered on.
class CartController extends StateNotifier<List<CartItem>> {
  CartController() : super([]);

  void addProduct(Product product) {
    final index = state.indexWhere((item) => item.product.id == product.id);

    if (index == -1) {
      state = [...state, CartItem(product: product, quantity: 1)];

      return;
    }

    state = [
      for (final item in state)
        if (item.product.id == product.id) item.copyWith(quantity: item.quantity + 1) else item,
    ];
  }

  void setQuantity(String productId, double quantity) {
    if (quantity <= 0) {
      removeProduct(productId);

      return;
    }

    state = [
      for (final item in state)
        if (item.product.id == productId) item.copyWith(quantity: quantity) else item,
    ];
  }

  void setDiscount(String productId, double discountAmount) {
    state = [
      for (final item in state)
        if (item.product.id == productId) item.copyWith(discountAmount: discountAmount) else item,
    ];
  }

  void removeProduct(String productId) {
    state = state.where((item) => item.product.id != productId).toList();
  }

  void clear() {
    state = [];
  }
}

final cartControllerProvider = StateNotifierProvider<CartController, List<CartItem>>((ref) {
  return CartController();
});

final cartTotalsProvider = Provider<CartTotals>((ref) {
  final items = ref.watch(cartControllerProvider);

  if (items.isEmpty) {
    return CartTotals.zero;
  }

  final subtotal = items.fold<double>(0, (sum, item) => sum + item.lineSubtotal);
  final discount = items.fold<double>(0, (sum, item) => sum + item.discountAmount);
  final tax = items.fold<double>(0, (sum, item) => sum + item.lineTax);
  final total = items.fold<double>(0, (sum, item) => sum + item.lineTotal);

  return CartTotals(subtotal: subtotal, discountAmount: discount, taxAmount: tax, totalAmount: total);
});
