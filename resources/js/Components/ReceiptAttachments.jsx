import StoredDocuments from '@/Components/StoredDocuments';

/**
 * Supporting documents on a receipt (bank payment confirmations, delivery notes …).
 * They are only stored here and never counted as spending.
 */
export default function ReceiptAttachments({ receiptId, attachments, max }) {
    return (
        <div className="mt-6">
            <StoredDocuments
                title="Additional documents"
                description="Bank payment confirmations, delivery notes and other supporting files. They are not counted as expenses."
                documents={attachments}
                max={max}
                accept=".jpg,.jpeg,.png,.heic,.heif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.eml"
                uploadUrl={route('receipts.attachments.store', receiptId)}
                removeUrl={(attachment) => route('receipts.attachments.destroy', [receiptId, attachment.id])}
            />
        </div>
    );
}
