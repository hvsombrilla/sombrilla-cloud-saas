<?php

namespace SombrillaCloud;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Studio\Models\SsOption;

/** Idempotente: crea solo las tablas que faltan. Todas llevan el prefijo scloud_. */
class Migrations
{
    private const OPTION = 'scloud_db_version';

    public static function run(): void
    {
        try {
            if (SsOption::get(self::OPTION) === SCLOUD_DB_VERSION) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $schema = Capsule::schema();

        if (!$schema->hasTable('scloud_customers')) {
            $schema->create('scloud_customers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('contact_id')->nullable()->index(); // contacto del ERP
                $t->string('name');
                $t->string('company')->nullable();
                $t->string('email')->unique();
                $t->string('password');
                $t->string('phone', 50)->nullable();
                $t->string('tax_id', 40)->nullable();
                $t->string('address', 500)->nullable();
                $t->string('city', 100)->nullable();
                $t->string('country', 100)->nullable();
                $t->string('status', 20)->default('active'); // active | disabled
                $t->string('reset_token', 100)->nullable();
                $t->dateTime('reset_expires_at')->nullable();
                $t->dateTime('last_login_at')->nullable();
                $t->string('last_login_ip', 45)->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('scloud_plans')) {
            $schema->create('scloud_plans', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('slug')->unique();
                $t->string('category', 100)->default('Instancias');
                $t->string('tagline')->nullable();
                $t->text('description')->nullable();
                $t->text('features')->nullable(); // una característica por línea
                $t->unsignedBigInteger('product_id')->nullable(); // producto (servicio) del ERP
                foreach (array_keys(\SombrillaCloud\Support\Cycles::ALL) as $cycle) {
                    $t->decimal('price_' . $cycle, 16, 2)->nullable(); // null = ciclo no ofrecido
                }
                $t->decimal('setup_fee', 16, 2)->default(0);
                $t->boolean('is_featured')->default(false);
                $t->boolean('is_active')->default(true);
                $t->boolean('is_hidden')->default(false);
                $t->integer('sort_order')->default(0);
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('scloud_orders')) {
            $schema->create('scloud_orders', function (Blueprint $t) {
                $t->id();
                $t->string('code', 30)->unique();
                $t->unsignedBigInteger('customer_id')->index();
                $t->string('status', 20)->default('pending'); // pending | paid | active | cancelled
                $t->string('payment_method', 30)->default('bank_transfer');
                $t->decimal('total', 16, 2)->default(0);
                $t->unsignedBigInteger('invoice_id')->nullable()->index();
                $t->text('notes')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('scloud_services')) {
            $schema->create('scloud_services', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_id')->index();
                $t->unsignedBigInteger('plan_id')->index();
                $t->unsignedBigInteger('order_id')->nullable()->index();
                $t->string('subdomain', 40)->nullable()->index();   // {subdominio}.sombrilla.cloud
                $t->string('domain_mode', 10)->default('subdomain'); // subdomain | buy | own
                $t->string('domain')->nullable()->index();           // dominio propio o comprado
                $t->string('domain_status', 20)->nullable();         // registering | dns_pending | connected | failed
                $t->decimal('domain_price', 16, 2)->default(0);      // lo cobrado al cliente por el dominio (1.er año)
                $t->decimal('domain_cost_usd', 10, 2)->nullable();   // costo en Cloudflare
                $t->date('domain_expires_at')->nullable();
                $t->string('workspace_name', 60)->nullable();
                $t->string('admin_email')->nullable();               // administrador de la instancia
                $t->dateTime('provisioned_at')->nullable();
                $t->string('billing_cycle', 20)->default('monthly');
                $t->decimal('amount', 16, 2)->default(0); // precio recurrente sin impuestos
                $t->string('status', 20)->default('pending'); // pending | active | suspended | terminated | cancelled | failed
                $t->date('registration_date')->nullable();
                $t->date('next_due_date')->nullable()->index();
                $t->string('suspend_reason')->nullable();
                $t->text('last_error')->nullable();
                $t->boolean('cancel_requested')->default(false);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // Facturas del ERP generadas por Sombrilla Cloud (pedido nuevo o renovación de un servicio).
        if (!$schema->hasTable('scloud_invoices')) {
            $schema->create('scloud_invoices', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('invoice_id')->unique(); // erp_invoices.id
                $t->unsignedBigInteger('customer_id')->index();
                $t->string('kind', 20); // order | renewal
                $t->unsignedBigInteger('order_id')->nullable()->index();
                $t->unsignedBigInteger('service_id')->nullable()->index();
                $t->date('period_from')->nullable();
                $t->date('period_to')->nullable();
                $t->dateTime('processed_at')->nullable(); // pagada y aplicada (servicio activado/renovado)
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('scloud_payment_proofs')) {
            $schema->create('scloud_payment_proofs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('invoice_id')->index();
                $t->unsignedBigInteger('customer_id')->index();
                $t->unsignedBigInteger('bank_account_id')->nullable();
                $t->decimal('amount', 16, 2)->default(0);
                $t->date('paid_at')->nullable();
                $t->string('reference', 100)->nullable();
                $t->string('file')->nullable();
                $t->text('notes')->nullable();
                $t->string('status', 20)->default('pending'); // pending | approved | rejected
                $t->text('review_notes')->nullable();
                $t->unsignedBigInteger('reviewed_by')->nullable();
                $t->dateTime('reviewed_at')->nullable();
                $t->unsignedBigInteger('payment_id')->nullable(); // erp_payments.id
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('scloud_bank_accounts')) {
            $schema->create('scloud_bank_accounts', function (Blueprint $t) {
                $t->id();
                $t->string('bank');
                $t->string('account_type', 50)->default('Corriente');
                $t->string('account_number', 60);
                $t->string('holder');
                $t->string('holder_id', 40)->nullable();
                $t->string('currency', 10)->default('DOP');
                $t->text('instructions')->nullable();
                $t->boolean('is_active')->default(true);
                $t->integer('sort_order')->default(0);
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('scloud_tickets')) {
            $schema->create('scloud_tickets', function (Blueprint $t) {
                $t->id();
                $t->string('code', 20)->unique();
                $t->unsignedBigInteger('customer_id')->index();
                $t->unsignedBigInteger('service_id')->nullable();
                $t->string('department', 30)->default('support');
                $t->string('subject');
                $t->string('priority', 10)->default('medium'); // low | medium | high
                $t->string('status', 20)->default('open');     // open | answered | customer_reply | on_hold | closed
                $t->unsignedBigInteger('assigned_to')->nullable();
                $t->dateTime('last_reply_at')->nullable();
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('scloud_ticket_replies')) {
            $schema->create('scloud_ticket_replies', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('ticket_id')->index();
                $t->string('author_type', 10); // customer | staff
                $t->unsignedBigInteger('author_id')->nullable();
                $t->string('author_name')->nullable();
                $t->text('message');
                $t->string('attachment')->nullable();
                $t->string('attachment_name')->nullable();
                $t->boolean('is_internal')->default(false); // nota interna (solo staff)
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('scloud_logs')) {
            $schema->create('scloud_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('service_id')->nullable()->index();
                $t->unsignedBigInteger('customer_id')->nullable()->index();
                $t->string('action', 50);
                $t->boolean('ok')->default(true);
                $t->text('message')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();
            });
        }

        self::upgrade($schema);

        SsOption::set(self::OPTION, SCLOUD_DB_VERSION);
    }

    /**
     * v2: el servicio pasa de cuentas cPanel/WHM a instancias de Studio. Añade las columnas nuevas y ELIMINA lo de WHM,
     * incluida la tabla de servidores con sus API tokens y las contraseñas de cPanel guardadas. Idempotente.
     */
    private static function upgrade($schema): void
    {
        $add = [
            'subdomain'         => fn(Blueprint $t) => $t->string('subdomain', 40)->nullable()->index(),
            'domain_mode'       => fn(Blueprint $t) => $t->string('domain_mode', 10)->default('subdomain'),
            'domain_status'     => fn(Blueprint $t) => $t->string('domain_status', 20)->nullable(),
            'domain_price'      => fn(Blueprint $t) => $t->decimal('domain_price', 16, 2)->default(0),
            'domain_cost_usd'   => fn(Blueprint $t) => $t->decimal('domain_cost_usd', 10, 2)->nullable(),
            'domain_expires_at' => fn(Blueprint $t) => $t->date('domain_expires_at')->nullable(),
            'workspace_name'    => fn(Blueprint $t) => $t->string('workspace_name', 60)->nullable(),
            'admin_email'       => fn(Blueprint $t) => $t->string('admin_email')->nullable(),
            'provisioned_at'    => fn(Blueprint $t) => $t->dateTime('provisioned_at')->nullable(),
        ];
        foreach ($add as $column => $definition) {
            if (!$schema->hasColumn('scloud_services', $column)) {
                $schema->table('scloud_services', fn(Blueprint $t) => $definition($t));
            }
        }

        // Primero se vacían los secretos y después se quitan las columnas.
        if ($schema->hasColumn('scloud_services', 'password')) {
            Capsule::table('scloud_services')->update(['password' => null]);
        }
        if ($schema->hasTable('scloud_servers')) {
            Capsule::table('scloud_servers')->update(['api_token' => null]);
            $schema->drop('scloud_servers');
        }
        $drop = [
            'scloud_services' => ['server_id', 'username', 'password', 'whm_package'],
            'scloud_plans'    => ['server_id', 'whm_package', 'requires_domain'],
        ];
        foreach ($drop as $table => $columns) {
            foreach ($columns as $column) {
                if (!$schema->hasColumn($table, $column)) {
                    continue;
                }
                try {
                    try {
                        $schema->table($table, fn(Blueprint $t) => $t->dropIndex([$column]));
                    } catch (\Throwable) {
                        // la columna no tenía índice
                    }
                    $schema->table($table, fn(Blueprint $t) => $t->dropColumn($column));
                } catch (\Throwable $e) {
                    error_log("[sombrilla-cloud] no se pudo quitar {$table}.{$column}: " . $e->getMessage());
                }
            }
        }

        // Ajustes que ya no existen.
        try {
            $settings = SsOption::get('scloud_settings', []);
            if (is_array($settings)) {
                unset($settings['auto_provision'], $settings['username_length']);
                SsOption::set('scloud_settings', $settings);
            }
        } catch (\Throwable) {
        }
    }
}
