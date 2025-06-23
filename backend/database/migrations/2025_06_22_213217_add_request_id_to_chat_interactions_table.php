<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRequestIdToChatInteractionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('chat_interactions', function (Blueprint $table) {
            $table->string('request_id', 100)->nullable()->after('id');
            $table->json('query_analysis')->nullable()->after('context_used');
            $table->json('quality_metrics')->nullable()->after('query_analysis');
            $table->json('escalation_reasons')->nullable()->after('requires_human_follow_up');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chat_interactions', function (Blueprint $table) {
            $table->dropColumn(['request_id', 'query_analysis', 'quality_metrics', 'escalation_reasons']);
        });
    }
}
