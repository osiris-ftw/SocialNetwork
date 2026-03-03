<?php

namespace App\Models;

class Report extends BaseModel
{
    protected string $table = 'reports';

    public function createReport(
        int $reporterId,
        string $entityType,
        int $entityId,
        string $reason,
        ?string $description = null
    ): int {
        return $this->create([
            'reporter_id' => $reporterId,
            'reported_entity_type' => $entityType,
            'reported_entity_id' => $entityId,
            'reason' => $reason,
            'description' => $description,
            'status' => 'pending',
        ]);
    }

    public function getReports(string $status = null, int $limit = 50, int $offset = 0): array
    {
        if ($status) {
            return $this->query(
                "SELECT r.*, 
                        reporter.username as reporter_username,
                        reviewer.username as reviewer_username
                 FROM reports r
                 INNER JOIN users reporter ON r.reporter_id = reporter.id
                 LEFT JOIN users reviewer ON r.reviewed_by = reviewer.id
                 WHERE r.status = ?
                 ORDER BY r.created_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
                [$status]
            );
        }

        return $this->query(
            "SELECT r.*, 
                    reporter.username as reporter_username,
                    reviewer.username as reviewer_username
             FROM reports r
             INNER JOIN users reporter ON r.reporter_id = reporter.id
             LEFT JOIN users reviewer ON r.reviewed_by = reviewer.id
             ORDER BY r.created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
    }

    public function getPendingReports(int $limit = 50): array
    {
        return $this->getReports('pending', $limit, 0);
    }

    public function updateReportStatus(
        int $reportId,
        int $reviewerId,
        string $status,
        ?string $resolutionNotes = null
    ): bool {
        return $this->update($reportId, [
            'status' => $status,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'resolution_notes' => $resolutionNotes,
        ]);
    }

    public function getReportedContent(int $reportId): ?array
    {
        $report = $this->find($reportId);
        
        if (!$report) {
            return null;
        }

        $entityType = $report['reported_entity_type'];
        $entityId = $report['reported_entity_id'];

        switch ($entityType) {
            case 'post':
                $post = new Post();
                return $post->find($entityId);
            
            case 'comment':
                $comment = new Comment();
                return $comment->find($entityId);
            
            case 'user':
                $user = new User();
                return $user->find($entityId);
            
            default:
                return null;
        }
    }

    public function getReportStats(): array
    {
        return $this->queryOne(
            "SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_count,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed_count
             FROM reports"
        );
    }
}
