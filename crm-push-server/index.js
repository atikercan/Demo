const
    io = require("socket.io"),
    server = io.listen(8001),
    in_array = require('in_array');

let admins = new Map(); 
let students = new Map(); 
let activeCalls = new Map();

// event fired every time a new client connects:
server.on("connection", (socket) => {
    // when socket connects, add it to the list:
    if(typeof(socket.handshake.query.id)!="undefined") {
        var aid = parseInt(socket.handshake.query.id);
        if(admins.has(aid)) {
            var sockets = admins.get(aid);
            sockets.push(socket);
            admins.set(aid, sockets);
        } else {
            admins.set(aid, [socket]);
        } 
        //console.log(aid + " idli bağantı sayısı: " + admins.get(aid).length);
    }
    if(typeof(socket.handshake.query.student_id)!="undefined") {
        var aid = parseInt(socket.handshake.query.student_id);
        if(students.has(aid)) {
            var sockets = students.get(aid);
            sockets.push(socket);
            students.set(aid, sockets);
        } else {
            students.set(aid, [socket]);
        } 
        //console.log(aid + "s idli bağantı sayısı: " + students.get(aid).length);
    }
    // when socket disconnects, remove it from the list:
    socket.on("disconnect", () => {
        for (var [aid, asockets] of admins.entries()) {
            if(in_array(socket,asockets)) {
                var sockets = admins.get(aid);
                sockets.splice(sockets.indexOf(socket), 1);
                if(sockets.length>0) {
                    admins.set(aid, sockets);
                } else {
                    admins.delete(aid);
                }
                //console.log(aid + " idli bağantı sayısı: " + sockets.length);
            }
        }
        for (var [aid, asockets] of students.entries()) {
            if(in_array(socket,asockets)) {
                var sockets = students.get(aid);
                sockets.splice(sockets.indexOf(socket), 1);
                if(sockets.length>0) {
                    students.set(aid, sockets);
                } else {
                    students.delete(aid);
                }
                //console.log(aid + "s idli bağantı sayısı: " + sockets.length);
            }
        }
    });

    // Voip Status Changed
    socket.on('voip.status-changed', function (data) { 
        if(typeof(data.admin_id)=='undefined' || typeof(data.status)=='undefined' || !admins.has(data.admin_id)) {
            return ;
        } 
        
        var sockets = admins.get(data.admin_id);  
        emitMultiple(sockets,'voip.status-changed',data,[]);
    });

    socket.on('voip.incoming-call.ring', function (data) { 
        if(typeof(data.admin_id)=='undefined' || typeof(data.num)=='undefined' || typeof(data.extension)=='undefined' || typeof(data.uniqueid)=='undefined' || !admins.has(data.admin_id)) {
            return ;
        } 
        //call unique id'yi active call listesine ekleyelim/düzenleyelim
        if(activeCalls.has(data.uniqueid)) {
            var previousCaller = activeCalls.get(data.uniqueid);
            var sockets = admins.get(previousCaller);  
            emitMultiple(sockets,'voip.incoming-call.hangup',data,[]);
        }
        activeCalls.set(data.uniqueid, data.admin_id);

        var sockets = admins.get(data.admin_id);  
        emitMultiple(sockets,'voip.incoming-call.ring',data,[]);
    });
    socket.on('voip.incoming-call.answer', function (data) { 
        if(typeof(data.admin_id)=='undefined' || typeof(data.num)=='undefined' || typeof(data.extension)=='undefined' || !admins.has(data.admin_id)) {
            var sockets = admins.get(data.admin_id); 
            return ;
        }
        var sockets = admins.get(data.admin_id);  
        emitMultiple(sockets,'voip.incoming-call.answer',data,[]);
    });
    socket.on('voip.incoming-call.hangup', function (data) { 
        if(typeof(data.uniqueid)=='undefined') { 
            return ;
        }
        //call unique id'yi active call listesine ekleyelim/düzenleyelim
        if(activeCalls.has(data.uniqueid)) {
            var aid = activeCalls.get(data.uniqueid);
            var sockets = admins.get(aid);  
            emitMultiple(sockets,'voip.incoming-call.hangup',data,[]);
            activeCalls.delete(data.uniqueid);
        }  
    });

    socket.on('messages.set-unread-count', function (data) { 
        if(typeof(data.admin_id)=='undefined' || !admins.has(data.admin_id)) {
            if(typeof(data.student_id)=='undefined' || !students.has(data.student_id)) {
                return ;
            } else {
                var sockets = students.get(data.student_id);  
                emitMultiple(sockets,'messages.set-unread-count',data,[]);
            }
        }
        
        var sockets = admins.get(data.admin_id);  
        emitMultiple(sockets,'messages.set-unread-count',data,[]);
    });
}); 

function findUserIdFromSocket(socket) {
    var user = false;

    for (var [aid, asockets] of admins.entries()) {
        if(in_array(socket,asockets)) {
            user = aid;
        }
    }

    return user;
}

function emitMultiple(sockets, msg, data,excepts) {
    for(i=0;i<sockets.length;i++) { 
        if(!in_array(sockets[i],excepts)) {
            sockets[i].emit(msg, data);
        }
    }
}
function clone(x)
{
    if (x === null || x === undefined)
        return x;
    if (typeof x.clone === "function")
        return x.clone();
    if (x.constructor == Array)
    {
        var r = [];
        for (var i=0,n=x.length; i<n; i++)
            r.push(clone(x[i]));
        return r;
    }
    return x;
}
 