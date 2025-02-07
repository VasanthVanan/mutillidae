<?php

    class UnsupportedHttpMethodException extends Exception {
        public function __construct($message) {
            parent::__construct($message);
        }
    }

    function populatePOSTSuperGlobal() {
        $lParameters = [];
        parse_str(file_get_contents('php://input'), $lParameters);
        $_POST = $lParameters + $_POST;
    }

    try {
        $lVerb = $_SERVER['REQUEST_METHOD'];
        $lDomain = $_SERVER['SERVER_NAME'];
        $lDomainParts = array_reverse(explode('.', $lDomain));
        $lParentDomain = $lDomainParts[1] . '.' . $lDomainParts[0];
        $lReturnData = true;

        // Populate $_POST if necessary for certain methods
        if (in_array($lVerb, ["PUT", "PATCH", "DELETE"])) {
            populatePOSTSuperGlobal();
        }

        // Retrieve max-age value from the request, defaulting to 600 seconds
        $lMaxAge = $_GET['acma'] ?? $_POST['acma'] ?? 600;

        // Get message from either GET or POST, defaulting to "Hello"
        $lMessageContent = $_GET['message'] ?? $_POST['message'] ?? 'Hello';
        $lMessage = '';

        // Process based on HTTP method
        switch ($lVerb) {
            case "OPTIONS":
                $lReturnData = false;
                break;
            case "GET":
                $lMessage = "GET request received";
                break;
            case "POST":
                $lMessage = "POST request processed";
                break;
            case "PUT":
                $lMessage = "PUT request - resource created or updated";
                break;
            case "PATCH":
                $lMessage = "PATCH request - partial update successful";
                break;
            case "DELETE":
                $lMessage = "DELETE request - resource removed";
                break;
            default:
                throw new UnsupportedHttpMethodException("Unsupported HTTP method: $lVerb");
        }

        // Construct the final message
        $lMessageText = "Message received: " . $lMessageContent . ". " . $lMessage . ".";

        // Set CORS headers dynamically
        if ($lVerb == "OPTIONS" ||
            ($_GET['acao'] ?? '') == "True" || ($_POST['acao'] ?? '') == "True") {
            header("Access-Control-Allow-Origin: {$_SERVER['REQUEST_SCHEME']}://{$lParentDomain}");
        }

        if ($lVerb == "OPTIONS" ||
            ($_GET['acam'] ?? '') == "True" || ($_POST['acam'] ?? '') == "True") {
            header("Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, PATCH, DELETE");
        }

        // Apply the max-age header with the provided or default value
        if ($lVerb == "OPTIONS") {
            header("Access-Control-Max-Age: $lMaxAge");
        }

        // Return JSON response if needed
        if ($lReturnData) {
            header(CONTENT_TYPE_JSON);
            echo json_encode([
                "Message" => $lMessageText,
                "Method" => $lVerb,
                "Parameters" => [
                    "GET" => $_GET,
                    "POST" => $_POST
                ],
                "Max-Age" => $lMaxAge
            ]);
        }
    } catch (Exception $e) {
        header(CONTENT_TYPE_JSON);
        header("Access-Control-Allow-Origin: {$_SERVER['REQUEST_SCHEME']}://{$lParentDomain}");
        echo json_encode([
            "Error" => $e->getMessage(),
            "Method" => $lVerb,
            "Parameters" => [
                "GET" => $_GET,
                "POST" => $_POST
            ]
        ], JSON_PRETTY_PRINT);
    }
?>
