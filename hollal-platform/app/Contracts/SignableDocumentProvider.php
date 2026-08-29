<?php

namespace App\Contracts;

use App\Models\SignatureRequest;

/**
 * Provider that binds a document type to the platform signature portal.
 */
interface SignableDocumentProvider
{
    public function documentType(): string;

    /** Human-readable title shown on the sign page. */
    public function title(SignatureRequest $request): string;

    /** Short summary lines for the sign page. @return list<string> */
    public function summaryLines(SignatureRequest $request): array;

    /** Optional PDF bytes for download; null when not available. */
    public function pdfBytes(SignatureRequest $request): ?string;

    /**
     * Apply the captured signature onto the underlying record.
     * $padDataUri is a PNG data URL from the canvas.
     */
    public function applySignature(
        SignatureRequest $request,
        string $padDataUri,
        string $signerName,
        string $signerPosition,
    ): void;
}
