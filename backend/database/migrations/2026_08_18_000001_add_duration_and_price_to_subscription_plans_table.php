<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plans become durations rather than tiers.
 *
 * The old shape was four feature tiers (Starter/Growth/Enterprise/Lifetime),
 * each carrying three prices — monthly, quarterly, yearly — with the
 * billing cycle chosen separately at checkout. The commercial model is now
 * one product sold in three lengths: 3, 6 and 12 months. A plan therefore
 * has exactly one duration and exactly one price.
 *
 * The old price columns are deliberately left in place rather than dropped:
 * `priceFor()` and existing subscription rows still read them, and dropping
 * a column in the same change that reshapes the data means a failure part
 * way through leaves no way back. They can go once nothing references them.
 *
 * Both new columns are nullable so the migration is safe on a database that
 * already holds plans — those rows get NULL and are handled by the seeder,
 * rather than the migration inventing a duration for a tier that never had
 * one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // How long one purchase of this plan lasts. NULL means the row
            // predates this model.
            $table->unsignedSmallInteger('duration_months')->nullable()->after('description');

            // The single price for that duration. Kept separate from the
            // legacy monthly/quarterly/yearly trio so there is one obvious
            // answer to "what does this cost" rather than three columns
            // where two are meaningless.
            $table->decimal('price', 12, 2)->nullable()->after('duration_months');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['duration_months', 'price']);
        });
    }
};
