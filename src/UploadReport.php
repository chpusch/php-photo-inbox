<?php

declare(strict_types=1);

namespace PhotoInbox;

/**
 * Ergebnis eines Upload-Vorgangs - wandert per Session-Flash durch das
 * Post/Redirect/Get und wird danach verworfen.
 */
final class UploadReport
{
    /**
     * @param list<SavedFile>    $saved
     * @param list<RejectedFile> $rejected
     * @param list<string>       $errors  Fehler, die den ganzen Request betreffen
     */
    public function __construct(
        public readonly array $saved = [],
        public readonly array $rejected = [],
        public readonly array $errors = [],
    ) {
    }

    public static function failure(string ...$errors): self
    {
        return new self(errors: array_values($errors));
    }

    public function isEmpty(): bool
    {
        return $this->saved === [] && $this->rejected === [] && $this->errors === [];
    }

    public function hasProblems(): bool
    {
        return $this->rejected !== [] || $this->errors !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'saved' => array_map(static fn (SavedFile $f): array => [
                'originalName' => $f->originalName,
                'storedName'   => $f->storedName,
                'bytes'        => $f->bytes,
                'format'       => $f->format->value,
                'formatLabel'  => $f->format->label(),
            ], $this->saved),
            'rejected' => array_map(static fn (RejectedFile $f): array => [
                'originalName' => $f->originalName,
                'reason'       => $f->reason,
            ], $this->rejected),
            'errors' => $this->errors,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $saved = [];
        foreach ((array) ($data['saved'] ?? []) as $row) {
            $format = ImageFormat::tryFrom(strtolower((string) ($row['format'] ?? '')));
            if ($format === null) {
                continue;
            }
            $saved[] = new SavedFile(
                (string) ($row['originalName'] ?? ''),
                (string) ($row['storedName'] ?? ''),
                (int) ($row['bytes'] ?? 0),
                $format,
            );
        }

        $rejected = [];
        foreach ((array) ($data['rejected'] ?? []) as $row) {
            $rejected[] = new RejectedFile(
                (string) ($row['originalName'] ?? ''),
                (string) ($row['reason'] ?? ''),
            );
        }

        return new self($saved, $rejected, array_map(strval(...), (array) ($data['errors'] ?? [])));
    }
}
