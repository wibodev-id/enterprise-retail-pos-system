<?php

/**
 * Approval Workflow Service
 * 
 * Demonstrates multi-level approval pattern for enterprise applications.
 * Used for critical operations like product edits, stock adjustments,
 * and transaction deletions.
 * 
 * @note This is a portfolio sample, not production code.
 */

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Interface for approvable requests.
 * Any entity that needs approval workflow should implement this.
 */
interface ApprovableRequest
{
    public function approve(User $approver): bool;
    public function reject(User $approver, string $reason): bool;
    public function execute(): void;
    public function getRequiredRoles(): array;
}

/**
 * Base trait for approval functionality.
 * Provides common approval methods for different entity types.
 */
trait HasApprovalWorkflow
{
    const STATUS_PENDING = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECTED = 3;

    /**
     * Check if user can approve this request.
     */
    public function canBeApprovedBy(User $user): bool
    {
        return $user->hasAnyRole($this->getRequiredRoles());
    }

    /**
     * Approve the request and execute the action.
     */
    public function approve(User $approver): bool
    {
        if (!$this->canBeApprovedBy($approver)) {
            throw new \Exception('User does not have permission to approve this request');
        }

        if ($this->status_id !== self::STATUS_PENDING) {
            throw new \Exception('Request is not in pending status');
        }

        return DB::transaction(function () use ($approver) {
            $this->update([
                'status_id' => self::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now()
            ]);

            // Execute the actual operation
            $this->execute();

            // Log the approval
            $this->logActivity('approved', $approver);

            return true;
        });
    }

    /**
     * Reject the request with a reason.
     */
    public function reject(User $approver, string $reason): bool
    {
        if (!$this->canBeApprovedBy($approver)) {
            throw new \Exception('User does not have permission to reject this request');
        }

        $this->update([
            'status_id' => self::STATUS_REJECTED,
            'rejected_by' => $approver->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason
        ]);

        $this->logActivity('rejected', $approver, $reason);

        return true;
    }

    /**
     * Log approval activity for audit trail.
     */
    protected function logActivity(string $action, User $user, ?string $notes = null): void
    {
        DB::table('approval_logs')->insert([
            'approvable_type' => get_class($this),
            'approvable_id' => $this->id,
            'action' => $action,
            'user_id' => $user->id,
            'notes' => $notes,
            'created_at' => now()
        ]);
    }
}

/**
 * Example: Product Edit Request
 * Requires supervisor or admin approval before changes take effect.
 */
class EditProductRequest extends Model implements ApprovableRequest
{
    use HasApprovalWorkflow;

    protected $table = 'edit_product_requests';

    protected $fillable = [
        'product_id',
        'requested_by',
        'new_title',
        'new_price',
        'new_category_id',
        'reason',
        'status_id',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason'
    ];

    public function getRequiredRoles(): array
    {
        return ['supervisor', 'admin', 'it'];
    }

    /**
     * Execute the product edit after approval.
     */
    public function execute(): void
    {
        $product = Product::findOrFail($this->product_id);

        $product->update([
            'title' => $this->new_title ?? $product->title,
            'price' => $this->new_price ?? $product->price,
            'category_id' => $this->new_category_id ?? $product->category_id,
        ]);
    }

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

/**
 * Example: Stock Adjustment Request
 * Requires approval for stock corrections to maintain inventory integrity.
 */
class StockAdjustmentRequest extends Model implements ApprovableRequest
{
    use HasApprovalWorkflow;

    protected $table = 'stock_adjustment_requests';

    protected $fillable = [
        'product_id',
        'location_id',
        'requested_by',
        'adjustment_type', // 'add', 'subtract', 'set'
        'quantity',
        'reason',
        'status_id'
    ];

    public function getRequiredRoles(): array
    {
        return ['supervisor', 'it'];
    }

    public function execute(): void
    {
        $stock = Stock::where('product_id', $this->product_id)
            ->where('location_id', $this->location_id)
            ->firstOrFail();

        switch ($this->adjustment_type) {
            case 'add':
                $stock->increment('quantity', $this->quantity);
                break;
            case 'subtract':
                $stock->decrement('quantity', $this->quantity);
                break;
            case 'set':
                $stock->update(['quantity' => $this->quantity]);
                break;
        }
    }
}

/**
 * Example: Transaction Delete Request
 * Requires director-level approval for transaction deletions.
 */
class DeleteTransactionRequest extends Model implements ApprovableRequest
{
    use HasApprovalWorkflow;

    protected $table = 'delete_transaction_requests';

    public function getRequiredRoles(): array
    {
        // Higher authority needed for financial data
        return ['director', 'it'];
    }

    public function execute(): void
    {
        $transaction = Transaction::findOrFail($this->transaction_id);

        // Soft delete to maintain audit trail
        $transaction->update([
            'status' => 'deleted',
            'deleted_at' => now(),
            'deleted_reason' => $this->reason
        ]);

        // Restore stock quantities
        foreach ($transaction->details as $detail) {
            Stock::where('product_id', $detail->product_id)
                ->where('location_id', $transaction->location_id)
                ->increment('quantity', $detail->quantity);
        }
    }
}

/**
 * Approval Service - Central manager for all approval workflows.
 */
class ApprovalService
{
    /**
     * Get all pending requests for a user based on their roles.
     */
    public function getPendingRequestsForUser(User $user): array
    {
        $requests = [];

        // Check each approval type based on user roles
        if ($user->hasAnyRole(['supervisor', 'admin', 'it'])) {
            $requests['product_edits'] = EditProductRequest::where('status_id', 1)->get();
            $requests['stock_adjustments'] = StockAdjustmentRequest::where('status_id', 1)->get();
        }

        if ($user->hasAnyRole(['director', 'it'])) {
            $requests['transaction_deletes'] = DeleteTransactionRequest::where('status_id', 1)->get();
        }

        return $requests;
    }

    /**
     * Get approval statistics for dashboard.
     */
    public function getStatistics(): array
    {
        return [
            'pending' => $this->countByStatus(1),
            'approved_today' => $this->countApprovedToday(),
            'rejected_today' => $this->countRejectedToday(),
        ];
    }

    protected function countByStatus(int $status): int
    {
        return EditProductRequest::where('status_id', $status)->count()
            + StockAdjustmentRequest::where('status_id', $status)->count()
            + DeleteTransactionRequest::where('status_id', $status)->count();
    }

    protected function countApprovedToday(): int
    {
        $today = now()->startOfDay();

        return EditProductRequest::where('status_id', 2)->where('approved_at', '>=', $today)->count()
            + StockAdjustmentRequest::where('status_id', 2)->where('approved_at', '>=', $today)->count()
            + DeleteTransactionRequest::where('status_id', 2)->where('approved_at', '>=', $today)->count();
    }

    protected function countRejectedToday(): int
    {
        $today = now()->startOfDay();

        return EditProductRequest::where('status_id', 3)->where('rejected_at', '>=', $today)->count()
            + StockAdjustmentRequest::where('status_id', 3)->where('rejected_at', '>=', $today)->count()
            + DeleteTransactionRequest::where('status_id', 3)->where('rejected_at', '>=', $today)->count();
    }
}
