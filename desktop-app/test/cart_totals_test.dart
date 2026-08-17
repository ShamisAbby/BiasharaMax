import 'package:biasharamax_desktop/data/local/database.dart';
import 'package:biasharamax_desktop/features/pos/cart/cart_controller.dart';
import 'package:biasharamax_desktop/features/pos/cart/cart_item.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Replaces the `widget_test.dart` scaffold `flutter create` generates,
/// which referenced the template's `MyApp` and was the only *error*
/// `flutter analyze` reported.
///
/// It tests the cart maths rather than a widget on purpose. Everything
/// else in this app is a thin shell over the server — the server recomputes
/// every sale from `product_id` + `quantity` on sync and is the source of
/// truth for what gets charged. The one thing that is genuinely computed on
/// the client is the number on the screen the cashier reads out loud, and a
/// till that quotes a different total from the one it later charges is a
/// dispute at the counter, not a rendering bug.
///
/// These need no database, no HTTP and no `ProviderScope` overrides, so
/// they run on a bare `dart test` from a clean checkout.
void main() {
  group('CartItem', () {
    test('discounts the line before tax is applied, not after', () {
      final item = CartItem(
        product: _product(sellingPrice: 100, taxRate: 18),
        quantity: 1,
        discountAmount: 20,
      );

      // The ordering is the whole point. Taxing first and discounting after
      // would give 98.00 here — a 2.40 overcharge on a single line, and
      // one that reconciles against nothing when the server disagrees.
      expect(item.lineSubtotal, 100);
      expect(item.taxableAmount, 80);
      expect(item.lineTax, 14.40);
      expect(item.lineTotal, 94.40);
    });

    test('rounds to two decimals rather than carrying float error forward', () {
      final item = CartItem(product: _product(sellingPrice: 19.99), quantity: 3);

      expect(item.lineSubtotal, 59.97);
    });
  });

  group('cartTotalsProvider', () {
    test('is zero for an empty cart', () {
      final container = ProviderContainer();
      addTearDown(container.dispose);

      final totals = container.read(cartTotalsProvider);

      expect(totals.subtotal, 0);
      expect(totals.totalAmount, 0);
    });

    test('sums tax per line rather than over the cart total', () {
      final container = ProviderContainer();
      addTearDown(container.dispose);

      final cart = container.read(cartControllerProvider.notifier);
      cart.addProduct(_product(id: 'a', sellingPrice: 100, taxRate: 18));
      cart.addProduct(_product(id: 'b', sellingPrice: 50, taxRate: 0));

      final totals = container.read(cartTotalsProvider);

      expect(totals.subtotal, 150);
      // 18 on the first line and nothing on the second. A cart-level rate
      // would tax the zero-rated item too — which is exactly the mistake a
      // mixed basket of taxable and exempt goods is there to catch.
      expect(totals.taxAmount, 18);
      expect(totals.totalAmount, 168);
    });

    test('scanning the same product twice adds quantity, not a second line', () {
      final container = ProviderContainer();
      addTearDown(container.dispose);

      final product = _product(sellingPrice: 100);
      final cart = container.read(cartControllerProvider.notifier);
      cart.addProduct(product);
      cart.addProduct(product);

      expect(container.read(cartControllerProvider), hasLength(1));
      expect(container.read(cartControllerProvider).single.quantity, 2);
      expect(container.read(cartTotalsProvider).subtotal, 200);
    });

    test('setting a quantity of zero removes the line entirely', () {
      final container = ProviderContainer();
      addTearDown(container.dispose);

      final cart = container.read(cartControllerProvider.notifier);
      cart.addProduct(_product(id: 'a', sellingPrice: 100));
      cart.setQuantity('a', 0);

      // A zero-quantity line left in the cart would sync to the server as a
      // zero-quantity sale item; dropping it is what keeps the pushed
      // payload equal to what the cashier sees.
      expect(container.read(cartControllerProvider), isEmpty);
      expect(container.read(cartTotalsProvider).totalAmount, 0);
    });
  });
}

Product _product({
  String id = 'p1',
  double sellingPrice = 0,
  double taxRate = 0,
}) {
  return Product(
    id: id,
    name: 'Test product',
    productType: 'simple',
    trackStock: true,
    costPrice: 0,
    sellingPrice: sellingPrice,
    taxRate: taxRate,
    status: 'active',
    updatedAt: DateTime(2026, 1, 1),
  );
}
