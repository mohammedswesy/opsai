<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Legal details for the invoice letterhead. legal_name, tax_number, phone and email
 * already exist on organizations; this adds only the missing address fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'address')) {
                $table->string('address', 500)->nullable()->after('email');
            }

            if (! Schema::hasColumn('organizations', 'city')) {
                $table->string('city', 120)->nullable()->after('address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['address', 'city']);
        });
    }
};
