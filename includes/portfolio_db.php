<?php
require_once 'firebase.php';

// Get all projects
function getProjects($featuredOnly = false) {
    try {
        $database = initializeFirebase();
        if (!$database) return [];

        $ref = $database->getReference('projects');
        $snapshot = $ref->getSnapshot();

        if (!$snapshot->exists()) return [];

        $projects = [];
        foreach ($snapshot->getValue() as $key => $project) {
            $project['id'] = $key;
            if (!$featuredOnly || ($featuredOnly && !empty($project['featured']))) {
                $projects[] = $project;
            }
        }

        // Sort by created_at DESC if exists
        usort($projects, function ($a, $b) {
            return strtotime($b['created_at'] ?? '1970-01-01') < strtotime($a['created_at'] ?? '1970-01-01');
        });

        return $projects;
    } catch (Exception $e) {
        error_log('Error fetching projects: ' . $e->getMessage());
        return [];
    }
}
?>