import '../../core/api/api_client.dart';
import '../../core/api/endpoints.dart';

/// The branches and warehouses this business sells from.
///
/// Deliberately fetched rather than derived from synced inventory rows,
/// which is what the warehouse picker used to do — that only ever yielded
/// bare UUIDs, and only for warehouses that happened to already hold
/// stock. A brand-new warehouse with nothing in it was invisible.
class LocationApi {
  LocationApi(this._client);

  final ApiClient _client;

  Future<LocationOptions> fetch() async {
    final response = await _client.get(Endpoints.locations);
    final body = response.data as Map<String, dynamic>;

    return LocationOptions(
      branches: (body['data'] as List)
          .cast<Map<String, dynamic>>()
          .map(BranchOption.fromJson)
          .toList(),
      defaultBranchId: body['default_branch_id'] as String?,
    );
  }
}

class LocationOptions {
  const LocationOptions({required this.branches, this.defaultBranchId});

  final List<BranchOption> branches;

  /// The signed-in employee's own branch, when they have one — used to
  /// preselect rather than to decide, so a till at a different branch can
  /// still be pointed anywhere.
  final String? defaultBranchId;
}

class BranchOption {
  const BranchOption({
    required this.id,
    required this.name,
    this.city,
    this.warehouses = const [],
  });

  factory BranchOption.fromJson(Map<String, dynamic> json) => BranchOption(
        id: json['id'] as String,
        name: json['name'] as String,
        city: json['city'] as String?,
        warehouses: (json['warehouses'] as List? ?? [])
            .cast<Map<String, dynamic>>()
            .map(WarehouseOption.fromJson)
            .toList(),
      );

  final String id;
  final String name;
  final String? city;
  final List<WarehouseOption> warehouses;
}

class WarehouseOption {
  const WarehouseOption({required this.id, required this.name, this.isDefault = false});

  factory WarehouseOption.fromJson(Map<String, dynamic> json) => WarehouseOption(
        id: json['id'] as String,
        name: json['name'] as String,
        isDefault: json['is_default'] == true,
      );

  final String id;
  final String name;
  final bool isDefault;
}
