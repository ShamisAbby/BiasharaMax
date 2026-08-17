import '../../../data/local/database.dart';

/// One line in the cart. Mirrors what
/// `SaleService::buildLineItems()` computes server-side (see
/// app/Modules/Sales/Services/SaleService.php) so the total the cashier
/// sees on screen matches what the server will actually charge — the
/// server is still the source of truth (it recomputes everything from
/// `product_id` + `quantity` on sync), this is purely a client-side
/// preview so the cashier isn't surprised later.
class CartItem {
  CartItem({
    required this.product,
    required this.quantity,
    double? unitPrice,
    this.discountAmount = 0,
  }) : unitPrice = unitPrice ?? product.sellingPrice;

  final Product product;
  final double quantity;
  final double unitPrice;
  final double discountAmount;

  CartItem copyWith({double? quantity, double? unitPrice, double? discountAmount}) {
    return CartItem(
      product: product,
      quantity: quantity ?? this.quantity,
      unitPrice: unitPrice ?? this.unitPrice,
      discountAmount: discountAmount ?? this.discountAmount,
    );
  }

  double get lineSubtotal => _round(quantity * unitPrice);

  double get taxableAmount => _round(lineSubtotal - discountAmount);

  double get lineTax => _round(taxableAmount * (product.taxRate / 100));

  double get lineTotal => _round(taxableAmount + lineTax);

  Map<String, dynamic> toSalePayload() => {
        'product_id': product.id,
        'quantity': quantity,
        'unit_price': unitPrice,
        'discount_amount': discountAmount,
      };
}

/// Two-decimal rounding, matching the server's `bcadd(..., 2)` /
/// `bcmul(..., 2)` scale for money — not exact decimal arithmetic
/// (Dart doubles aren't), but close enough for an on-screen preview that
/// the server always re-derives authoritatively on sync.
double _round(double value) => double.parse(value.toStringAsFixed(2));
